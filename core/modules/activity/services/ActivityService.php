<?php

declare(strict_types=1);

namespace Stratum\Modules\Activity;

use Stratum\Core\Database;
use Stratum\Core\ModuleManager;

/**
 * Live UNION-ALL "recent activity" stream across whichever content modules
 * are currently enabled — the same discipline SearchService established:
 * no persistent activity table to keep in sync (this codebase has no
 * generic content-lifecycle hooks), no `requires` edge on any content
 * module, each branch skipped entirely via ModuleManager::isEnabled() so
 * disabling a module makes its activity vanish gracefully. Always current,
 * zero write-path changes anywhere, and history is complete from day one —
 * an event-sourced table would have started empty and never shown anything
 * that predated it.
 *
 * Actor usernames are deliberately NOT joined here — branches return a raw
 * actor_id and the controller resolves names via AuthService::findById()
 * (soft-delete-aware, "Unknown" fallback), the same controller-side pattern
 * org_spaces settled on after its raw-join-ignores-deleted_at bug.
 */
final class ActivityService
{
    private const ITEM_LIMIT = 40;

    /** @var array<string, array{label: string, verb: string, urlPrefix: ?string}> */
    private const ACTIVITY_TYPES = [
        'member' => ['label' => 'New member', 'verb' => 'joined the community', 'urlPrefix' => null],
        'forum_topic' => ['label' => 'Forum', 'verb' => 'started a topic', 'urlPrefix' => '/forum/topics/'],
        'article' => ['label' => 'Article', 'verb' => 'published an article', 'urlPrefix' => '/articles/'],
        'wiki_page' => ['label' => 'Wiki', 'verb' => 'created a wiki page', 'urlPrefix' => '/wiki/'],
        'calendar_event' => ['label' => 'Event', 'verb' => 'added an event', 'urlPrefix' => '/calendar/events/'],
        'download' => ['label' => 'Download', 'verb' => 'shared a file', 'urlPrefix' => '/downloads/files/'],
        'video' => ['label' => 'Video', 'verb' => 'added a video', 'urlPrefix' => '/videos/'],
        'gallery_album' => ['label' => 'Gallery', 'verb' => 'posted a photo album', 'urlPrefix' => '/gallery/albums/'],
        'classified' => ['label' => 'Classifieds', 'verb' => 'posted a listing', 'urlPrefix' => '/classifieds/listings/'],
        'org_announcement' => ['label' => 'Announcement', 'verb' => 'posted an announcement', 'urlPrefix' => '/organizations/'],
    ];

    public function __construct(
        private readonly Database $db,
        private readonly ModuleManager $modules
    ) {
    }

    /** @return array<int, array{content_type: string, label: string, verb: string, title: string, url: ?string, actor_id: ?int, created_at: string}> */
    public function recent(): array
    {
        $branches = array_filter([
            $this->membersBranch(),
            $this->forumTopicsBranch(),
            $this->articlesBranch(),
            $this->wikiPagesBranch(),
            $this->calendarEventsBranch(),
            $this->downloadsBranch(),
            $this->videosBranch(),
            $this->galleryAlbumsBranch(),
            $this->classifiedsBranch(),
            $this->orgAnnouncementsBranch(),
        ]);

        if ($branches === []) {
            return [];
        }

        $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $branches) . ') AS combined_activity'
            . ' ORDER BY created_at DESC'
            . ' LIMIT ' . self::ITEM_LIMIT;

        $items = [];
        foreach ($this->db->fetchAll($sql) as $row) {
            $type = self::ACTIVITY_TYPES[$row['content_type']];
            $items[] = [
                'content_type' => $row['content_type'],
                'label' => $type['label'],
                'verb' => $type['verb'],
                'title' => $row['title'],
                'url' => $type['urlPrefix'] !== null ? $type['urlPrefix'] . $row['url_key'] : null,
                'actor_id' => $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
                'created_at' => $row['created_at'],
            ];
        }

        return $items;
    }

    /** New members are their own subject — title IS the username, so actor_id stays NULL and no link (no public profile page exists yet). */
    private function membersBranch(): string
    {
        $table = $this->db->table('users');

        return "SELECT 'member' AS content_type, username AS title, NULL AS url_key, NULL AS actor_id, created_at AS created_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    private function forumTopicsBranch(): ?string
    {
        if (!$this->modules->isEnabled('forum')) {
            return null;
        }

        $table = $this->db->table('forum_topics');

        return "SELECT 'forum_topic' AS content_type, title AS title, id AS url_key, author_id AS actor_id, created_at AS created_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    private function articlesBranch(): ?string
    {
        if (!$this->modules->isEnabled('articles')) {
            return null;
        }

        $table = $this->db->table('articles');

        // Matches ArticleService::PUBLISHED_CONDITION exactly — a scheduled
        // article appears in the feed the instant it goes live, same
        // reasoning as search's branch (2026-07-17).
        return "SELECT 'article' AS content_type, title AS title, slug AS url_key, author_id AS actor_id, created_at AS created_at
            FROM {$table}
            WHERE deleted_at IS NULL AND (is_published = 1 OR (published_at IS NOT NULL AND published_at <= NOW()))";
    }

    /** A wiki page has no author column — its creator is the earliest revision's author. */
    private function wikiPagesBranch(): ?string
    {
        if (!$this->modules->isEnabled('wiki')) {
            return null;
        }

        $pages = $this->db->table('wiki_pages');
        $revisions = $this->db->table('wiki_revisions');

        return "SELECT 'wiki_page' AS content_type, wp.title AS title, wp.slug AS url_key,
            (SELECT author_id FROM {$revisions} WHERE page_id = wp.id ORDER BY created_at ASC, id ASC LIMIT 1) AS actor_id,
            wp.created_at AS created_at
            FROM {$pages} wp
            WHERE wp.deleted_at IS NULL";
    }

    /** Recurring events materialize as up to 26 rows sharing a series_id — only the first occurrence per series is one activity item, not 26. */
    private function calendarEventsBranch(): ?string
    {
        if (!$this->modules->isEnabled('calendar')) {
            return null;
        }

        $table = $this->db->table('calendar_events');

        return "SELECT 'calendar_event' AS content_type, ce.title AS title, ce.id AS url_key, ce.author_id AS actor_id, ce.created_at AS created_at
            FROM {$table} ce
            WHERE ce.deleted_at IS NULL
            AND (ce.series_id IS NULL OR ce.id = (SELECT MIN(id) FROM {$table} WHERE series_id = ce.series_id))";
    }

    /** A downloads file has no uploader column — its sharer is version 1's uploader. */
    private function downloadsBranch(): ?string
    {
        if (!$this->modules->isEnabled('downloads')) {
            return null;
        }

        $files = $this->db->table('downloads_files');
        $versions = $this->db->table('downloads_versions');

        return "SELECT 'download' AS content_type, df.title AS title, df.id AS url_key,
            (SELECT uploader_id FROM {$versions} WHERE file_id = df.id ORDER BY version_number ASC LIMIT 1) AS actor_id,
            df.created_at AS created_at
            FROM {$files} df
            WHERE df.deleted_at IS NULL";
    }

    private function videosBranch(): ?string
    {
        if (!$this->modules->isEnabled('video')) {
            return null;
        }

        $table = $this->db->table('videos');

        return "SELECT 'video' AS content_type, title AS title, id AS url_key, uploader_id AS actor_id, created_at AS created_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    /**
     * An album has no uploader column — its creator is the earliest surviving
     * photo's uploader (albums are never created empty per Stage 5c; if every
     * photo has since been soft-deleted this resolves to "Unknown"). Photos
     * appended to an existing album later are deliberately not separate
     * activity items — album creation is the user-action granularity here.
     */
    private function galleryAlbumsBranch(): ?string
    {
        if (!$this->modules->isEnabled('gallery')) {
            return null;
        }

        $albums = $this->db->table('gallery_albums');
        $photos = $this->db->table('gallery_photos');

        return "SELECT 'gallery_album' AS content_type, ga.title AS title, ga.id AS url_key,
            (SELECT uploader_id FROM {$photos} WHERE album_id = ga.id AND deleted_at IS NULL ORDER BY id ASC LIMIT 1) AS actor_id,
            ga.created_at AS created_at
            FROM {$albums} ga
            WHERE ga.deleted_at IS NULL";
    }

    private function classifiedsBranch(): ?string
    {
        if (!$this->modules->isEnabled('classifieds')) {
            return null;
        }

        $table = $this->db->table('classifieds_listings');

        // Sold listings stay included (they stay publicly visible per Stage 6c) — only soft-deleted ones are excluded.
        return "SELECT 'classified' AS content_type, title AS title, id AS url_key, user_id AS actor_id, created_at AS created_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    private function orgAnnouncementsBranch(): ?string
    {
        if (!$this->modules->isEnabled('org_spaces')) {
            return null;
        }

        $announcements = $this->db->table('org_spaces_announcements');
        $orgs = $this->db->table('org_spaces_orgs');

        // No deleted_at on announcements — visibility is governed by the parent org's is_active flag, same as search's branch.
        return "SELECT 'org_announcement' AS content_type, a.title AS title, o.slug AS url_key, a.author_id AS actor_id, a.created_at AS created_at
            FROM {$announcements} a
            INNER JOIN {$orgs} o ON o.id = a.org_id
            WHERE o.is_active = 1";
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Builds a member's self-service data export — their own account fields
 * plus a manifest of content they've authored across whichever modules
 * are enabled, gated the same "compute live, no cached index" way
 * TrashService/SearchService/ActivityService already established for
 * "enumerate content across every enabled module for one purpose."
 *
 * Only content types with a clean, single-column author attribution are
 * included: `gallery_albums` and `downloads_files` have no author
 * column of their own (only their child photos/versions do), so they're
 * deliberately left out rather than guessed at with a fragile join.
 */
final class AccountExportService
{
    public function __construct(
        private readonly Database $db,
        private readonly ModuleManager $modules
    ) {
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function export(array $user): array
    {
        $userId = (int) $user['id'];

        $content = array_filter([
            'forum_topics' => $this->modules->isEnabled('forum')
                ? $this->authoredRows('forum_topics', 'author_id', $userId, 'title', "CONCAT('/forum/topics/', id)")
                : null,
            'forum_posts' => $this->modules->isEnabled('forum') ? $this->forumPosts($userId) : null,
            'wiki_pages_edited' => $this->modules->isEnabled('wiki') ? $this->wikiPagesEdited($userId) : null,
            'calendar_events' => $this->modules->isEnabled('calendar')
                ? $this->authoredRows('calendar_events', 'author_id', $userId, 'title', "CONCAT('/calendar/events/', id)")
                : null,
            'classifieds_listings' => $this->modules->isEnabled('classifieds')
                ? $this->authoredRows('classifieds_listings', 'user_id', $userId, 'title', "CONCAT('/classifieds/listings/', id)")
                : null,
            'gallery_photos' => $this->modules->isEnabled('gallery') ? $this->galleryPhotos($userId) : null,
        ], static fn (?array $v): bool => $v !== null);

        return [
            'exported_at' => date('Y-m-d H:i:s'),
            'account' => [
                'username' => $user['username'],
                'email' => $user['email'],
                'about_me' => $user['about_me'],
                'avatar_url' => $user['avatar_url'],
                'banner_url' => $user['banner_url'],
                'signature' => $user['signature'],
                'points' => (int) ($user['points'] ?? 0),
                'joined_at' => $user['created_at'],
            ],
            'content' => $content,
        ];
    }

    /** @return array<int, array{title: string, url: string, created_at: string}> */
    private function authoredRows(string $table, string $authorColumn, int $userId, string $titleColumn, string $urlExpr): array
    {
        $tbl = $this->db->table($table);

        return $this->db->fetchAll(
            "SELECT {$titleColumn} AS title, {$urlExpr} AS url, created_at
             FROM {$tbl} WHERE {$authorColumn} = :user_id AND deleted_at IS NULL
             ORDER BY created_at DESC",
            ['user_id' => $userId]
        );
    }

    /** @return array<int, array{title: string, url: string, created_at: string}> */
    private function forumPosts(int $userId): array
    {
        $posts = $this->db->table('forum_posts');
        $topics = $this->db->table('forum_topics');

        return $this->db->fetchAll(
            "SELECT CONCAT('Reply in \"', t.title, '\"') AS title, CONCAT('/forum/topics/', t.id) AS url, p.created_at
             FROM {$posts} p
             INNER JOIN {$topics} t ON t.id = p.topic_id
             WHERE p.author_id = :user_id AND p.deleted_at IS NULL
             ORDER BY p.created_at DESC",
            ['user_id' => $userId]
        );
    }

    /** Wiki pages have no author of their own — this lists pages this member has contributed a revision to, not "pages they own" (wiki is communal content). */
    private function wikiPagesEdited(int $userId): array
    {
        $revisions = $this->db->table('wiki_revisions');
        $pages = $this->db->table('wiki_pages');

        return $this->db->fetchAll(
            "SELECT DISTINCT wp.title AS title, CONCAT('/wiki/', wp.slug) AS url, wp.created_at
             FROM {$revisions} wr
             INNER JOIN {$pages} wp ON wp.id = wr.page_id
             WHERE wr.author_id = :user_id AND wp.deleted_at IS NULL
             ORDER BY wp.created_at DESC",
            ['user_id' => $userId]
        );
    }

    private function galleryPhotos(int $userId): array
    {
        $photos = $this->db->table('gallery_photos');

        return $this->db->fetchAll(
            "SELECT COALESCE(caption, CONCAT('Photo #', id)) AS title, CONCAT('/gallery/photos/', id) AS url, created_at
             FROM {$photos} WHERE uploader_id = :user_id AND deleted_at IS NULL
             ORDER BY created_at DESC",
            ['user_id' => $userId]
        );
    }
}

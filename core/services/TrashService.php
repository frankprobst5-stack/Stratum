<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Admin trash/recycle bin — a UI over soft-deleted rows that already exist
 * correctly across every content module (every module in this app
 * soft-deletes; nothing here changes that discipline, this just makes it
 * visible/reversible instead of requiring raw DB access). One registry of
 * "how to list + restore this type," same per-type-map pattern
 * ContentResolver/SearchService/ActivityService already established —
 * adding a new type means one more case in each match(), not new
 * architecture.
 *
 * Deliberately excludes `users` — a soft-deleted user account has
 * different restore semantics (password state, session handling, capacity
 * concerns) than restoring a piece of content, and deserves its own
 * decision when picked up, not a bolt-on to this feature.
 *
 * v2 (2026-07-17) added the two v1 gaps: the polymorphic `comments` table
 * and the six org_spaces private-content tables — same table shape, same
 * pattern, exactly the mechanical "add a type" extension v1's docblock
 * predicted, not new architecture.
 */
final class TrashService
{
    /** @var array<string, array{label: string, module: ?string}> */
    private const TYPES = [
        'article' => ['label' => 'Article', 'module' => 'articles'],
        'page' => ['label' => 'Page', 'module' => 'pages'],
        'wiki_page' => ['label' => 'Wiki Page', 'module' => 'wiki'],
        'forum_topic' => ['label' => 'Forum Topic', 'module' => 'forum'],
        'forum_post' => ['label' => 'Forum Post', 'module' => 'forum'],
        'calendar_event' => ['label' => 'Calendar Event', 'module' => 'calendar'],
        'download' => ['label' => 'Download', 'module' => 'downloads'],
        'classified' => ['label' => 'Classifieds Listing', 'module' => 'classifieds'],
        'gallery_album' => ['label' => 'Gallery Album', 'module' => 'gallery'],
        'gallery_photo' => ['label' => 'Gallery Photo', 'module' => 'gallery'],
        'video' => ['label' => 'Video', 'module' => 'video'],
        'comment' => ['label' => 'Comment', 'module' => 'comments'],
        'org_forum_topic' => ['label' => 'Org Forum Topic', 'module' => 'org_spaces'],
        'org_forum_post' => ['label' => 'Org Forum Post', 'module' => 'org_spaces'],
        'org_calendar_event' => ['label' => 'Org Calendar Event', 'module' => 'org_spaces'],
        'org_file' => ['label' => 'Org File', 'module' => 'org_spaces'],
        'org_gallery_album' => ['label' => 'Org Gallery Album', 'module' => 'org_spaces'],
        'org_gallery_photo' => ['label' => 'Org Gallery Photo', 'module' => 'org_spaces'],
        'link' => ['label' => 'Link', 'module' => 'links'],
        'form' => ['label' => 'Form', 'module' => 'forms'],
        'video_playlist' => ['label' => 'Video Playlist', 'module' => 'video'],
    ];

    public function __construct(
        private readonly Database $db,
        private readonly ModuleManager $modules
    ) {
    }

    /** @return array<int, array{type: string, label: string, id: int, title: string, url: string, deleted_at: string}> newest-deleted first */
    public function listTrashed(): array
    {
        $results = [];
        foreach (self::TYPES as $type => $meta) {
            if ($meta['module'] !== null && !$this->modules->isEnabled($meta['module'])) {
                continue; // disabled module's soft-deleted rows stay in the DB untouched, just not shown — same as every other isEnabled()-gated branch in this app
            }

            foreach ($this->fetchType($type) as $row) {
                $results[] = $row + ['type' => $type, 'label' => $meta['label']];
            }
        }

        usort($results, static fn (array $a, array $b): int => $b['deleted_at'] <=> $a['deleted_at']);

        return $results;
    }

    /** True if a row existed, was soft-deleted, and got restored. */
    public function restore(string $type, int $id): bool
    {
        $table = $this->tableFor($type);
        if ($table === null) {
            return false;
        }

        return $this->db->execute(
            "UPDATE {$table} SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        ) > 0;
    }

    private function tableFor(string $type): ?string
    {
        return match ($type) {
            'article' => $this->db->table('articles'),
            'page' => $this->db->table('pages'),
            'wiki_page' => $this->db->table('wiki_pages'),
            'forum_topic' => $this->db->table('forum_topics'),
            'forum_post' => $this->db->table('forum_posts'),
            'calendar_event' => $this->db->table('calendar_events'),
            'download' => $this->db->table('downloads_files'),
            'classified' => $this->db->table('classifieds_listings'),
            'gallery_album' => $this->db->table('gallery_albums'),
            'gallery_photo' => $this->db->table('gallery_photos'),
            'video' => $this->db->table('videos'),
            'comment' => $this->db->table('comments'),
            'org_forum_topic' => $this->db->table('org_spaces_forum_topics'),
            'org_forum_post' => $this->db->table('org_spaces_forum_posts'),
            'org_calendar_event' => $this->db->table('org_spaces_calendar_events'),
            'org_file' => $this->db->table('org_spaces_files'),
            'org_gallery_album' => $this->db->table('org_spaces_gallery_albums'),
            'org_gallery_photo' => $this->db->table('org_spaces_gallery_photos'),
            'link' => $this->db->table('links'),
            'form' => $this->db->table('forms'),
            'video_playlist' => $this->db->table('video_playlists'),
            default => null,
        };
    }

    /** @return array<int, array{id: int, title: string, url: string, deleted_at: string}> */
    private function fetchType(string $type): array
    {
        return match ($type) {
            'article' => $this->simpleRows('articles', 'title', "CONCAT('/articles/', slug)"),
            'page' => $this->simpleRows('pages', 'title', "CONCAT('/pages/', slug)"),
            'wiki_page' => $this->simpleRows('wiki_pages', 'title', "CONCAT('/wiki/', slug)"),
            'forum_topic' => $this->simpleRows('forum_topics', 'title', "CONCAT('/forum/topics/', id)"),
            'calendar_event' => $this->simpleRows('calendar_events', 'title', "CONCAT('/calendar/events/', id)"),
            'download' => $this->simpleRows('downloads_files', 'title', "CONCAT('/downloads/files/', id)"),
            'classified' => $this->simpleRows('classifieds_listings', 'title', "CONCAT('/classifieds/listings/', id)"),
            'gallery_album' => $this->simpleRows('gallery_albums', 'title', "CONCAT('/gallery/albums/', id)"),
            'video' => $this->simpleRows('videos', 'title', "CONCAT('/videos/', id)"),
            // No per-link show page exists (only the directory index and a
            // click-tracking redirect) — same "link to the index" reasoning
            // org_file's trash entry already uses.
            'link' => $this->simpleRows('links', 'title', "'/links'"),
            'form' => $this->simpleRows('forms', 'title', "CONCAT('/forms/', slug)"),
            'video_playlist' => $this->simpleRows('video_playlists', 'title', "CONCAT('/videos/playlists/', slug)"),
            'forum_post' => $this->childRows(
                child: 'forum_posts',
                parent: 'forum_topics',
                parentKey: 'topic_id',
                title: "CONCAT('Post in \"', p.title, '\"')",
                url: "CONCAT('/forum/topics/', c.topic_id)"
            ),
            'gallery_photo' => $this->childRows(
                child: 'gallery_photos',
                parent: 'gallery_albums',
                parentKey: 'album_id',
                title: "CONCAT('Photo in \"', p.title, '\"')",
                url: "CONCAT('/gallery/albums/', c.album_id)"
            ),
            'comment' => $this->commentRows(),
            'org_forum_topic' => $this->orgOwnedRows(
                table: 'org_spaces_forum_topics',
                title: 'c.title',
                url: "CONCAT('/organizations/', o.slug, '/forum/topics/', c.id)"
            ),
            'org_calendar_event' => $this->orgOwnedRows(
                table: 'org_spaces_calendar_events',
                title: 'c.title',
                url: "CONCAT('/organizations/', o.slug, '/calendar/events/', c.id)"
            ),
            // No per-file show page exists (org_spaces/routes.php only has an
            // index + a download action) — the URL links to the org's file
            // list, same reasoning downloads_versions restores would use if
            // that ever got its own trash entry.
            'org_file' => $this->orgOwnedRows(
                table: 'org_spaces_files',
                title: 'c.title',
                url: "CONCAT('/organizations/', o.slug, '/files')"
            ),
            'org_gallery_album' => $this->orgOwnedRows(
                table: 'org_spaces_gallery_albums',
                title: 'c.title',
                url: "CONCAT('/organizations/', o.slug, '/gallery/albums/', c.id)"
            ),
            'org_forum_post' => $this->orgChildRows(
                child: 'org_spaces_forum_posts',
                parent: 'org_spaces_forum_topics',
                parentKey: 'topic_id',
                title: "CONCAT('Post in \"', p.title, '\"')",
                url: "CONCAT('/organizations/', o.slug, '/forum/topics/', c.topic_id)"
            ),
            'org_gallery_photo' => $this->orgChildRows(
                child: 'org_spaces_gallery_photos',
                parent: 'org_spaces_gallery_albums',
                parentKey: 'album_id',
                title: "CONCAT('Photo in \"', p.title, '\"')",
                url: "CONCAT('/organizations/', o.slug, '/gallery/albums/', c.album_id)"
            ),
            default => [],
        };
    }

    /** A soft-deleted row that carries its own title/id — no parent join needed for a working URL. */
    private function simpleRows(string $table, string $titleColumn, string $urlExpr): array
    {
        $tbl = $this->db->table($table);

        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'title' => $r['title'], 'url' => $r['url'], 'deleted_at' => $r['deleted_at']],
            $this->db->fetchAll("SELECT id, {$titleColumn} AS title, {$urlExpr} AS url, deleted_at FROM {$tbl} WHERE deleted_at IS NOT NULL")
        );
    }

    /**
     * A soft-deleted row with no title of its own (forum posts, gallery
     * photos) — needs its parent joined to build a meaningful title/URL,
     * same reasoning ContentResolver's forumPost() case already
     * established. The parent isn't required to still exist for the child
     * row itself to show up here (an inner join would hide it silently);
     * a left join with a fallback keeps orphaned rows visible rather than
     * disappearing without explanation.
     */
    private function childRows(string $child, string $parent, string $parentKey, string $title, string $url): array
    {
        $childTbl = $this->db->table($child);
        $parentTbl = $this->db->table($parent);

        $rows = $this->db->fetchAll(
            "SELECT c.id, {$title} AS title, {$url} AS url, c.deleted_at
             FROM {$childTbl} c
             LEFT JOIN {$parentTbl} p ON p.id = c.{$parentKey}
             WHERE c.deleted_at IS NOT NULL"
        );

        return array_map(
            static fn (array $r): array => [
                'id' => (int) $r['id'],
                'title' => $r['title'] ?? '(parent no longer exists)',
                'url' => $r['url'],
                'deleted_at' => $r['deleted_at'],
            ],
            $rows
        );
    }

    /**
     * Comments are polymorphic (commentable_type/commentable_id) — unlike
     * every other type above, one `comments` table serves five unrelated
     * parent tables, so a single fixed join can't work here. Same
     * "UNION-ALL one branch per content type, each independently
     * module-gated" shape SearchService's and ActivityService's own
     * UNION-ALL branches already established, applied to trash instead of
     * search/activity.
     */
    private function commentRows(): array
    {
        $comments = $this->db->table('comments');
        $branches = [];

        if ($this->modules->isEnabled('articles')) {
            $t = $this->db->table('articles');
            $branches[] = "SELECT c.id, CONCAT('Comment on \"', a.title, '\"') AS title, CONCAT('/articles/', a.slug) AS url, c.deleted_at
                FROM {$comments} c LEFT JOIN {$t} a ON a.id = c.commentable_id
                WHERE c.commentable_type = 'article' AND c.deleted_at IS NOT NULL";
        }

        if ($this->modules->isEnabled('wiki')) {
            $t = $this->db->table('wiki_pages');
            $branches[] = "SELECT c.id, CONCAT('Comment on \"', w.title, '\"') AS title, CONCAT('/wiki/', w.slug) AS url, c.deleted_at
                FROM {$comments} c LEFT JOIN {$t} w ON w.id = c.commentable_id
                WHERE c.commentable_type = 'wiki_page' AND c.deleted_at IS NOT NULL";
        }

        if ($this->modules->isEnabled('video')) {
            $t = $this->db->table('videos');
            $branches[] = "SELECT c.id, CONCAT('Comment on \"', v.title, '\"') AS title, CONCAT('/videos/', v.id) AS url, c.deleted_at
                FROM {$comments} c LEFT JOIN {$t} v ON v.id = c.commentable_id
                WHERE c.commentable_type = 'video' AND c.deleted_at IS NOT NULL";
        }

        if ($this->modules->isEnabled('gallery')) {
            $t = $this->db->table('gallery_photos');
            // Photos have a nullable caption, not a title — a photo id is a
            // stable, always-present thing to name the comment after.
            $branches[] = "SELECT c.id, CONCAT('Comment on photo #', gp.id) AS title, CONCAT('/gallery/photos/', gp.id) AS url, c.deleted_at
                FROM {$comments} c LEFT JOIN {$t} gp ON gp.id = c.commentable_id
                WHERE c.commentable_type = 'gallery_photo' AND c.deleted_at IS NOT NULL";
        }

        if ($this->modules->isEnabled('calendar')) {
            $t = $this->db->table('calendar_events');
            $branches[] = "SELECT c.id, CONCAT('Comment on \"', ce.title, '\"') AS title, CONCAT('/calendar/events/', ce.id) AS url, c.deleted_at
                FROM {$comments} c LEFT JOIN {$t} ce ON ce.id = c.commentable_id
                WHERE c.commentable_type = 'calendar_event' AND c.deleted_at IS NOT NULL";
        }

        if ($branches === []) {
            return [];
        }

        $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $branches) . ') AS combined_comments';

        return array_map(
            static fn (array $r): array => [
                'id' => (int) $r['id'],
                'title' => $r['title'] ?? '(parent no longer exists)',
                'url' => $r['url'],
                'deleted_at' => $r['deleted_at'],
            ],
            $this->db->fetchAll($sql)
        );
    }

    /**
     * Same shape as simpleRows(), but every org_spaces content table is
     * scoped to a chapter and its public URL needs that chapter's slug —
     * always join org_spaces_orgs rather than teaching simpleRows() an
     * optional join only one family of types would ever use.
     */
    private function orgOwnedRows(string $table, string $title, string $url): array
    {
        $tbl = $this->db->table($table);
        $orgs = $this->db->table('org_spaces_orgs');

        $rows = $this->db->fetchAll(
            "SELECT c.id, {$title} AS title, {$url} AS url, c.deleted_at
             FROM {$tbl} c
             LEFT JOIN {$orgs} o ON o.id = c.org_id
             WHERE c.deleted_at IS NOT NULL"
        );

        return array_map(
            static fn (array $r): array => [
                'id' => (int) $r['id'],
                'title' => $r['title'],
                'url' => $r['url'] ?? '/organizations',
                'deleted_at' => $r['deleted_at'],
            ],
            $rows
        );
    }

    /**
     * Same shape as childRows(), but every org_spaces content table also
     * needs its chapter's slug for a working URL — one extra join beyond
     * the direct parent (child -> parent -> org), same "compute, don't
     * invent" reasoning as everywhere else in this class.
     */
    private function orgChildRows(string $child, string $parent, string $parentKey, string $title, string $url): array
    {
        $childTbl = $this->db->table($child);
        $parentTbl = $this->db->table($parent);
        $orgs = $this->db->table('org_spaces_orgs');

        $rows = $this->db->fetchAll(
            "SELECT c.id, {$title} AS title, {$url} AS url, c.deleted_at
             FROM {$childTbl} c
             LEFT JOIN {$parentTbl} p ON p.id = c.{$parentKey}
             LEFT JOIN {$orgs} o ON o.id = p.org_id
             WHERE c.deleted_at IS NOT NULL"
        );

        return array_map(
            static fn (array $r): array => [
                'id' => (int) $r['id'],
                'title' => $r['title'] ?? '(parent no longer exists)',
                'url' => $r['url'],
                'deleted_at' => $r['deleted_at'],
            ],
            $rows
        );
    }
}

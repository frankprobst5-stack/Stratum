<?php

declare(strict_types=1);

namespace Stratum\Modules\Search;

use Stratum\Core\Database;
use Stratum\Core\ModuleManager;

/**
 * Live UNION-ALL search across whichever content modules are currently
 * enabled — no persistent index table (see docs/roadmap.md's "Site Search"
 * section for why: no generic content-lifecycle hooks exist in this
 * codebase to keep an index in sync, and a cron-rebuilt index would mean
 * new content isn't searchable for up to a day). Uses LIKE, not
 * MATCH...AGAINST, since none of the source tables have a FULLTEXT index
 * and adding one would mean either altering tables this module doesn't own
 * or a hard `requires` on every content module — both rejected in the plan
 * for this feature. Fine at club-scale row counts; revisit only if a real
 * club's data volume makes it slow.
 */
final class SearchService
{
    private const RESULT_LIMIT = 30;
    private const MIN_QUERY_LENGTH = 2;
    private const SNIPPET_LENGTH = 160;
    private const SNIPPET_LEAD = 60;

    /** @var array<string, array{label: string, urlPrefix: string}> */
    private const CONTENT_TYPES = [
        'article' => ['label' => 'Article', 'urlPrefix' => '/articles/'],
        'wiki' => ['label' => 'Wiki', 'urlPrefix' => '/wiki/'],
        'forum_topic' => ['label' => 'Forum', 'urlPrefix' => '/forum/topics/'],
        'forum_post' => ['label' => 'Forum reply', 'urlPrefix' => '/forum/topics/'],
        'download' => ['label' => 'Download', 'urlPrefix' => '/downloads/files/'],
        'classified' => ['label' => 'Classifieds', 'urlPrefix' => '/classifieds/listings/'],
        'org_announcement' => ['label' => 'Announcement', 'urlPrefix' => '/organizations/'],
        'gallery_photo' => ['label' => 'Gallery Photo', 'urlPrefix' => '/gallery/photos/'],
        'video' => ['label' => 'Video', 'urlPrefix' => '/videos/'],
    ];

    private int $paramCounter = 0;

    public function __construct(
        private readonly Database $db,
        private readonly ModuleManager $modules
    ) {
    }

    /** @return array<int, array{content_type: string, label: string, title: string, snippet: string, url: string, created_at: string}> */
    public function search(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        // MySQL's default LIKE escape character is already backslash, which
        // matches addcslashes()'s default escape character below, so no
        // explicit ESCAPE clause is needed in the SQL.
        $like = '%' . addcslashes($term, '%_\\') . '%';

        $params = [];
        $branches = array_filter([
            $this->articlesBranch($params, $like),
            $this->wikiBranch($params, $like),
            $this->forumTopicsBranch($params, $like),
            $this->forumPostsBranch($params, $like),
            $this->downloadsBranch($params, $like),
            $this->classifiedsBranch($params, $like),
            $this->orgAnnouncementsBranch($params, $like),
            $this->galleryPhotosBranch($params, $like),
            $this->videosBranch($params, $like),
        ]);

        if ($branches === []) {
            return [];
        }

        $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $branches) . ') AS combined_results'
            . ' ORDER BY relevance DESC, created_at DESC'
            . ' LIMIT ' . self::RESULT_LIMIT;

        $rows = $this->db->fetchAll($sql, $params);

        $results = [];
        foreach ($rows as $row) {
            $type = self::CONTENT_TYPES[$row['content_type']];
            $results[] = [
                'content_type' => $row['content_type'],
                'label' => $type['label'],
                'title' => $row['title'],
                'snippet' => $this->makeSnippet($row['snippet_source'], $term),
                'url' => $type['urlPrefix'] . $row['url_key'],
                'created_at' => $row['created_at'],
            ];
        }

        return $results;
    }

    /** Every occurrence of a placeholder gets its own unique name — this codebase's PDO layer rejects a named placeholder reused twice in one query. */
    private function bindLike(array &$params, string $like): string
    {
        $name = 'p' . (++$this->paramCounter);
        $params[$name] = $like;

        return ':' . $name;
    }

    private function articlesBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('articles')) {
            return null;
        }

        $titleCase = $this->bindLike($params, $like);
        $titleWhere = $this->bindLike($params, $like);
        $bodyWhere = $this->bindLike($params, $like);
        $table = $this->db->table('articles');
        $revisions = $this->db->table('articles_revisions');

        // Matches ArticleService::PUBLISHED_CONDITION exactly — scheduled
        // publishing (2026-07-17) means is_published alone isn't
        // authoritative, since cron.daily only catches up once a day. A
        // scheduled article whose time has passed must show up in search
        // the instant it's live on its own page, not up to ~18 hours later.
        // articles.body moved to articles_revisions the same day (true
        // revision history, same shape wiki already had) — the join to the
        // latest revision for snippet/search text is the exact pattern
        // wiki's own branch already established below.
        return "SELECT 'article' AS content_type, a.title AS title, ar.body AS snippet_source, a.slug AS url_key, a.created_at AS created_at,
            (CASE WHEN a.title LIKE {$titleCase} THEN 2 ELSE 1 END) AS relevance
            FROM {$table} a
            INNER JOIN {$revisions} ar ON ar.id = (
                SELECT id FROM {$revisions} WHERE article_id = a.id ORDER BY created_at DESC, id DESC LIMIT 1
            )
            WHERE a.deleted_at IS NULL AND (a.is_published = 1 OR (a.published_at IS NOT NULL AND a.published_at <= NOW()))
            AND (a.title LIKE {$titleWhere} OR ar.body LIKE {$bodyWhere})";
    }

    private function wikiBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('wiki')) {
            return null;
        }

        $titleCase = $this->bindLike($params, $like);
        $titleWhere = $this->bindLike($params, $like);
        $bodyWhere = $this->bindLike($params, $like);
        $pages = $this->db->table('wiki_pages');
        $revisions = $this->db->table('wiki_revisions');

        return "SELECT 'wiki' AS content_type, wp.title AS title, wr.body AS snippet_source, wp.slug AS url_key, wp.created_at AS created_at,
            (CASE WHEN wp.title LIKE {$titleCase} THEN 2 ELSE 1 END) AS relevance
            FROM {$pages} wp
            INNER JOIN {$revisions} wr ON wr.id = (
                SELECT id FROM {$revisions} WHERE page_id = wp.id ORDER BY created_at DESC, id DESC LIMIT 1
            )
            WHERE wp.deleted_at IS NULL AND (wp.title LIKE {$titleWhere} OR wr.body LIKE {$bodyWhere})";
    }

    private function forumTopicsBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('forum')) {
            return null;
        }

        $titleWhere = $this->bindLike($params, $like);
        $table = $this->db->table('forum_topics');

        return "SELECT 'forum_topic' AS content_type, title AS title, NULL AS snippet_source, id AS url_key, created_at AS created_at,
            2 AS relevance
            FROM {$table}
            WHERE deleted_at IS NULL AND title LIKE {$titleWhere}";
    }

    private function forumPostsBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('forum')) {
            return null;
        }

        $bodyWhere = $this->bindLike($params, $like);
        $posts = $this->db->table('forum_posts');
        $topics = $this->db->table('forum_topics');

        return "SELECT 'forum_post' AS content_type, ft.title AS title, fp.body AS snippet_source, ft.id AS url_key, fp.created_at AS created_at,
            1 AS relevance
            FROM {$posts} fp
            INNER JOIN {$topics} ft ON ft.id = fp.topic_id
            WHERE fp.deleted_at IS NULL AND ft.deleted_at IS NULL AND fp.body LIKE {$bodyWhere}";
    }

    private function downloadsBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('downloads')) {
            return null;
        }

        $titleCase = $this->bindLike($params, $like);
        $titleWhere = $this->bindLike($params, $like);
        $descWhere = $this->bindLike($params, $like);
        $table = $this->db->table('downloads_files');

        return "SELECT 'download' AS content_type, title AS title, description AS snippet_source, id AS url_key, created_at AS created_at,
            (CASE WHEN title LIKE {$titleCase} THEN 2 ELSE 1 END) AS relevance
            FROM {$table}
            WHERE deleted_at IS NULL AND (title LIKE {$titleWhere} OR description LIKE {$descWhere})";
    }

    private function classifiedsBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('classifieds')) {
            return null;
        }

        $titleCase = $this->bindLike($params, $like);
        $titleWhere = $this->bindLike($params, $like);
        $descWhere = $this->bindLike($params, $like);
        $table = $this->db->table('classifieds_listings');

        // Sold listings deliberately stay included (they stay visible on the
        // public listing per Stage 6c) — only soft-deleted ones are excluded.
        return "SELECT 'classified' AS content_type, title AS title, description AS snippet_source, id AS url_key, created_at AS created_at,
            (CASE WHEN title LIKE {$titleCase} THEN 2 ELSE 1 END) AS relevance
            FROM {$table}
            WHERE deleted_at IS NULL AND (title LIKE {$titleWhere} OR description LIKE {$descWhere})";
    }

    private function orgAnnouncementsBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('org_spaces')) {
            return null;
        }

        $titleCase = $this->bindLike($params, $like);
        $titleWhere = $this->bindLike($params, $like);
        $bodyWhere = $this->bindLike($params, $like);
        $announcements = $this->db->table('org_spaces_announcements');
        $orgs = $this->db->table('org_spaces_orgs');

        // strat_org_spaces_announcements has no deleted_at column — visibility
        // is governed entirely by the parent org's is_active flag.
        return "SELECT 'org_announcement' AS content_type, a.title AS title, a.body AS snippet_source, o.slug AS url_key, a.created_at AS created_at,
            (CASE WHEN a.title LIKE {$titleCase} THEN 2 ELSE 1 END) AS relevance
            FROM {$announcements} a
            INNER JOIN {$orgs} o ON o.id = a.org_id
            WHERE o.is_active = 1 AND (a.title LIKE {$titleWhere} OR a.body LIKE {$bodyWhere})";
    }

    /** v1.1 addition — gallery photos have no title of their own, only an optional caption (same fallback AccountExportService's galleryPhotos() already uses). */
    private function galleryPhotosBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('gallery')) {
            return null;
        }

        $captionWhere = $this->bindLike($params, $like);
        $table = $this->db->table('gallery_photos');

        return "SELECT 'gallery_photo' AS content_type, COALESCE(caption, CONCAT('Photo #', id)) AS title, caption AS snippet_source, id AS url_key, created_at AS created_at,
            1 AS relevance
            FROM {$table}
            WHERE deleted_at IS NULL AND caption LIKE {$captionWhere}";
    }

    /** v1.1 addition — titles and descriptions of both hosted (YouTube/Vimeo) and uploaded videos. */
    private function videosBranch(array &$params, string $like): ?string
    {
        if (!$this->modules->isEnabled('video')) {
            return null;
        }

        $titleCase = $this->bindLike($params, $like);
        $titleWhere = $this->bindLike($params, $like);
        $descWhere = $this->bindLike($params, $like);
        $table = $this->db->table('videos');

        return "SELECT 'video' AS content_type, title AS title, description AS snippet_source, id AS url_key, created_at AS created_at,
            (CASE WHEN title LIKE {$titleCase} THEN 2 ELSE 1 END) AS relevance
            FROM {$table}
            WHERE deleted_at IS NULL AND (title LIKE {$titleWhere} OR description LIKE {$descWhere})";
    }

    private function makeSnippet(?string $raw, string $term): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        $plain = preg_replace('/\[\/?[a-z][a-z0-9]*(=[^\]]*)?\]/i', '', $raw) ?? $raw;
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? $plain);

        if ($plain === '') {
            return '';
        }

        $pos = mb_stripos($plain, $term);
        if ($pos === false) {
            return mb_substr($plain, 0, self::SNIPPET_LENGTH) . (mb_strlen($plain) > self::SNIPPET_LENGTH ? '…' : '');
        }

        $start = max(0, $pos - self::SNIPPET_LEAD);
        $snippet = mb_substr($plain, $start, self::SNIPPET_LENGTH);

        return ($start > 0 ? '…' : '') . $snippet . (mb_strlen($plain) > $start + self::SNIPPET_LENGTH ? '…' : '');
    }
}

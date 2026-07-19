<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Live UNION-ALL sitemap across whichever content modules are currently
 * enabled — same "no persistent index, compute it live" discipline
 * SearchService and ActivityService already established for this codebase
 * (no generic content-lifecycle hooks exist to keep a cached index in
 * sync). Club-scale row counts are fine to enumerate on every request;
 * revisit only if a real club's data volume makes /sitemap.xml slow.
 */
final class SitemapService
{
    public function __construct(
        private readonly Database $db,
        private readonly ModuleManager $modules
    ) {
    }

    /** Same "service builds the ready-to-send string" shape as ArticleFeedExporter::buildXml(). */
    public function buildXml(string $baseUrl): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($this->urls($baseUrl) as $url) {
            $xml .= '<url><loc>' . e($url['loc']) . '</loc>';
            if ($url['lastmod'] !== null) {
                $xml .= '<lastmod>' . e(date('Y-m-d', strtotime($url['lastmod']))) . '</lastmod>';
            }
            $xml .= '</url>';
        }

        return $xml . '</urlset>';
    }

    /** @return array<int, array{loc: string, lastmod: ?string}> */
    private function urls(string $baseUrl): array
    {
        $urls = [['loc' => $baseUrl . '/', 'lastmod' => null]];

        foreach ($this->indexPages() as $path) {
            $urls[] = ['loc' => $baseUrl . $path, 'lastmod' => null];
        }

        $branches = array_filter([
            $this->articlesBranch(),
            $this->pagesBranch(),
            $this->wikiBranch(),
            $this->downloadsBranch(),
            $this->videosBranch(),
            $this->galleryAlbumsBranch(),
            $this->classifiedsBranch(),
            $this->forumTopicsBranch(),
            $this->calendarEventsBranch(),
        ]);

        if ($branches !== []) {
            $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $branches) . ') AS combined_urls';
            foreach ($this->db->fetchAll($sql) as $row) {
                $urls[] = ['loc' => $baseUrl . $row['url'], 'lastmod' => $row['updated_at']];
            }
        }

        return $urls;
    }

    /** @return array<int, string> */
    private function indexPages(): array
    {
        $map = [
            'articles' => '/articles', 'wiki' => '/wiki', 'downloads' => '/downloads',
            'video' => '/videos', 'gallery' => '/gallery', 'classifieds' => '/classifieds',
            'forum' => '/forum', 'calendar' => '/calendar',
        ];

        $paths = [];
        foreach ($map as $moduleId => $path) {
            if ($this->modules->isEnabled($moduleId)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function articlesBranch(): ?string
    {
        if (!$this->modules->isEnabled('articles')) {
            return null;
        }

        $table = $this->db->table('articles');

        // Matches ArticleService::PUBLISHED_CONDITION exactly — same reason
        // search's branch does: a scheduled article must appear the instant
        // it goes live, not up to a day later once cron.daily catches up.
        return "SELECT CONCAT('/articles/', slug) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL AND (is_published = 1 OR (published_at IS NOT NULL AND published_at <= NOW()))";
    }

    private function pagesBranch(): ?string
    {
        if (!$this->modules->isEnabled('pages')) {
            return null;
        }

        $table = $this->db->table('pages');

        return "SELECT CONCAT('/pages/', slug) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL AND is_published = 1";
    }

    private function wikiBranch(): ?string
    {
        if (!$this->modules->isEnabled('wiki')) {
            return null;
        }

        $table = $this->db->table('wiki_pages');

        return "SELECT CONCAT('/wiki/', slug) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    private function downloadsBranch(): ?string
    {
        if (!$this->modules->isEnabled('downloads')) {
            return null;
        }

        $table = $this->db->table('downloads_files');

        return "SELECT CONCAT('/downloads/files/', id) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    private function videosBranch(): ?string
    {
        if (!$this->modules->isEnabled('video')) {
            return null;
        }

        $table = $this->db->table('videos');

        return "SELECT CONCAT('/videos/', id) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    /**
     * Albums only, not individual photos — an album page is the natural
     * shareable/indexable entry point, and a photo-per-URL sitemap entry
     * would balloon well past what's useful for a club-scale gallery.
     */
    private function galleryAlbumsBranch(): ?string
    {
        if (!$this->modules->isEnabled('gallery')) {
            return null;
        }

        $table = $this->db->table('gallery_albums');

        return "SELECT CONCAT('/gallery/albums/', id) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    private function classifiedsBranch(): ?string
    {
        if (!$this->modules->isEnabled('classifieds')) {
            return null;
        }

        $table = $this->db->table('classifieds_listings');

        // Sold listings stay included, matching SearchService's classifieds
        // branch — they're still real, visible pages until soft-deleted.
        return "SELECT CONCAT('/classifieds/listings/', id) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    private function forumTopicsBranch(): ?string
    {
        if (!$this->modules->isEnabled('forum')) {
            return null;
        }

        $table = $this->db->table('forum_topics');

        return "SELECT CONCAT('/forum/topics/', id) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }

    private function calendarEventsBranch(): ?string
    {
        if (!$this->modules->isEnabled('calendar')) {
            return null;
        }

        $table = $this->db->table('calendar_events');

        return "SELECT CONCAT('/calendar/events/', id) AS url, updated_at AS updated_at
            FROM {$table}
            WHERE deleted_at IS NULL";
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Modules\RssAggregator;

use Stratum\Core\BBCodeParser;
use Stratum\Core\Database;
use Stratum\Modules\Articles\ArticleService;
use Stratum\Modules\Users\AuthService;

final class ArticleFeedExporter
{
    public function __construct(private readonly Database $db)
    {
    }

    public function buildXml(string $baseUrl): string
    {
        $articles = new ArticleService($this->db);
        $authors = new AuthService($this->db);
        $bbcode = new BBCodeParser();

        $settingsRow = $this->db->fetchOne(
            'SELECT `value` FROM ' . $this->db->table('core_settings') . ' WHERE `key` = :key',
            ['key' => 'site_name']
        );
        $siteName = $settingsRow['value'] ?? 'Stratum CMS';

        $items = '';
        foreach ($articles->listPublished() as $article) {
            $url = $baseUrl . '/articles/' . $article['slug'];
            $author = $authors->findById((int) $article['author_id']);
            $authorName = $author['username'] ?? 'Unknown';
            // articles.body moved to articles_revisions (2026-07-17, true
            // revision history) — "current" is the latest revision, same
            // "compute, don't cache" resolution every other article read
            // path now uses.
            $revision = $articles->currentRevision((int) $article['id']);
            $description = $this->cdata($bbcode->render($revision['body'] ?? ''));
            $pubDate = $article['published_at'] !== null
                ? date('r', strtotime($article['published_at']))
                : date('r');

            $items .= '<item>'
                . '<title>' . e($article['title']) . '</title>'
                . '<link>' . e($url) . '</link>'
                . '<guid isPermaLink="true">' . e($url) . '</guid>'
                . '<pubDate>' . e($pubDate) . '</pubDate>'
                . '<dc:creator>' . e($authorName) . '</dc:creator>'
                . '<description>' . $description . '</description>'
                . '</item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<channel>'
            . '<title>' . e($siteName) . '</title>'
            . '<link>' . e($baseUrl) . '</link>'
            . '<description>' . e($siteName) . ' — Articles</description>'
            . $items
            . '</channel>'
            . '</rss>';
    }

    private function cdata(string $html): string
    {
        return '<![CDATA[' . str_replace(']]>', ']]&gt;', $html) . ']]>';
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Modules\RssAggregator;

use SimpleXMLElement;
use Stratum\Core\Database;
use Stratum\Modules\Articles\ArticleService;

final class RssFetcher
{
    private const TIMEOUT_SECONDS = 10;
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const USER_AGENT = 'Stratum RSS Aggregator/1.0 (+admin-configured feed reader)';

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array{success: bool, itemCount: int, error: ?string} */
    public function fetchAndStore(int $sourceId): array
    {
        $source = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('rss_sources') . ' WHERE id = :id',
            ['id' => $sourceId]
        );

        if ($source === null) {
            return ['success' => false, 'itemCount' => 0, 'error' => 'Source not found.'];
        }

        $now = date('Y-m-d H:i:s');

        try {
            $body = $this->fetchUrl($source['feed_url']);
            $items = $this->parseFeed($body);
        } catch (\Throwable $e) {
            $this->db->execute(
                'UPDATE ' . $this->db->table('rss_sources') . '
                 SET last_fetched_at = :now, last_fetch_error = :error WHERE id = :id',
                ['now' => $now, 'error' => substr($e->getMessage(), 0, 500), 'id' => $sourceId]
            );

            return ['success' => false, 'itemCount' => 0, 'error' => $e->getMessage()];
        }

        $inserted = 0;
        foreach ($items as $item) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM ' . $this->db->table('rss_items') . ' WHERE source_id = :source_id AND guid = :guid',
                ['source_id' => $sourceId, 'guid' => $item['guid']]
            );

            if ($existing !== null) {
                continue;
            }

            $articleId = ((bool) $source['auto_publish']) && $source['article_author_id'] !== null
                ? $this->publishAsArticle($source, $item)
                : null;

            $this->db->insert('rss_items', [
                'source_id' => $sourceId,
                'guid' => $item['guid'],
                'title' => $item['title'],
                'url' => $item['url'],
                'description' => $item['description'],
                'published_at' => $item['publishedAt'],
                'article_id' => $articleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        $this->db->execute(
            'UPDATE ' . $this->db->table('rss_sources') . '
             SET last_fetched_at = :now, last_fetch_error = NULL WHERE id = :id',
            ['now' => $now, 'id' => $sourceId]
        );

        return ['success' => true, 'itemCount' => $inserted, 'error' => null];
    }

    /**
     * Publishes a freshly-fetched item as a real site article — clearly
     * attributed to the source with a link back to the original, since
     * this is syndicated content, not this site's own writing. Published
     * immediately ('now'), not scheduled or held as a draft, since the
     * entire point of turning this on is a hands-off feed.
     *
     * @param array<string, mixed> $source
     * @param array{guid: string, title: string, url: string, description: ?string, publishedAt: ?string} $item
     */
    private function publishAsArticle(array $source, array $item): int
    {
        $body = '<p><em>Originally published by <a href="' . htmlspecialchars($item['url'], ENT_QUOTES) . '">'
            . htmlspecialchars($source['name'], ENT_QUOTES) . '</a>.</em></p>';

        if ($item['description'] !== null && $item['description'] !== '') {
            $body .= "\n\n" . $item['description'];
        }

        $result = (new ArticleService($this->db))->create([
            'category_id' => null,
            'author_id' => (int) $source['article_author_id'],
            'title' => $item['title'],
            'excerpt' => '',
            'body' => $body,
            'publish_action' => 'now',
            'scheduled_at' => null,
        ]);

        return $result['articleId'];
    }

    private function fetchUrl(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Feed URL must be http or https.');
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($errno !== 0) {
            throw new \RuntimeException("Fetch failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Feed returned HTTP {$status}.");
        }

        if ($body === false || $body === '') {
            throw new \RuntimeException('Feed response was empty.');
        }

        return $body;
    }

    /** @return array<int, array{guid: string, title: string, url: string, description: ?string, publishedAt: ?string}> */
    private function parseFeed(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();

        if ($doc === false || !isset($doc->channel->item)) {
            throw new \RuntimeException('Response was not a valid RSS 2.0 feed.');
        }

        $items = [];
        foreach ($doc->channel->item as $item) {
            $link = trim((string) $item->link);
            $guid = trim((string) $item->guid) ?: $link;
            $title = trim((string) $item->title);

            if ($guid === '' || $link === '' || $title === '') {
                continue;
            }

            $timestamp = strtotime((string) $item->pubDate);

            $items[] = [
                'guid' => substr($guid, 0, 500),
                'title' => substr($title, 0, 500),
                'url' => substr($link, 0, 500),
                'description' => $item->description !== null ? trim((string) $item->description) : null,
                'publishedAt' => $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null,
            ];
        }

        return $items;
    }
}

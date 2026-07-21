<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\ActivityApiController;
use Stratum\Modules\Articles\ArticleService;
use Tests\TestCase;

final class ActivityApiTest extends TestCase
{
    /** @var int[] article ids created by this test, cleaned up in tearDown() */
    private array $articleIds = [];

    protected function tearDown(): void
    {
        foreach ($this->articleIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('articles_revisions') . ' WHERE article_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('articles') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->articleIds = [];

        parent::tearDown();
    }

    public function testIndexIncludesRecentlyPublishedArticle(): void
    {
        $author = $this->createUser();
        $service = new ArticleService($this->db);
        $result = $service->create([
            'category_id' => null,
            'author_id' => (int) $author['id'],
            'title' => 'API test activity article ' . bin2hex(random_bytes(4)),
            'excerpt' => '',
            'body' => 'body',
            'publish_action' => 'now',
        ]);
        $this->articleIds[] = (int) $result['articleId'];
        $article = $service->find((int) $result['articleId']);

        $controller = new ActivityApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/activity'));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $matching = array_filter(
            $body['data'],
            static fn (array $item): bool => $item['content_type'] === 'article' && $item['title'] === $article['title']
        );
        $this->assertNotEmpty($matching);
    }
}

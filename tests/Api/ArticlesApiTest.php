<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\ArticlesApiController;
use Stratum\Modules\Articles\ArticleService;
use Tests\TestCase;

final class ArticlesApiTest extends TestCase
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

    /** @return array<string, mixed> */
    private function createArticle(int $authorId, string $title, bool $published): array
    {
        $service = new ArticleService($this->db);
        $result = $service->create([
            'category_id' => null,
            'author_id' => $authorId,
            'title' => $title,
            'excerpt' => '',
            'body' => "Body for {$title}",
            'publish_action' => $published ? 'now' : 'draft',
        ]);
        $this->articleIds[] = (int) $result['articleId'];

        return $service->find((int) $result['articleId']);
    }

    public function testIndexReturnsOnlyPublishedArticles(): void
    {
        $author = $this->createUser();
        $published = $this->createArticle((int) $author['id'], 'API test published ' . bin2hex(random_bytes(4)), true);
        $this->createArticle((int) $author['id'], 'API test draft ' . bin2hex(random_bytes(4)), false);

        $controller = new ArticlesApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/articles', ['per_page' => '100']);

        $response = $controller->index($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $slugs = array_column($body['data'], 'slug');
        $this->assertContains($published['slug'], $slugs);
    }

    public function testIndexPaginatesResults(): void
    {
        $author = $this->createUser();
        $prefix = 'API pagination ' . bin2hex(random_bytes(4)) . ' ';
        $this->createArticle((int) $author['id'], $prefix . 'A', true);
        $this->createArticle((int) $author['id'], $prefix . 'B', true);
        $this->createArticle((int) $author['id'], $prefix . 'C', true);

        $controller = new ArticlesApiController($this->app);

        $page1 = json_decode(
            $controller->index($this->makeRequest('GET', '/api/v1/articles', ['per_page' => '2', 'page' => '1']))->body(),
            true
        );
        $this->assertCount(2, $page1['data']);
        $this->assertGreaterThanOrEqual(3, $page1['meta']['total']);
        $this->assertSame(1, $page1['meta']['page']);
    }

    public function testShowReturnsPublishedArticleBySlug(): void
    {
        $author = $this->createUser();
        $article = $this->createArticle((int) $author['id'], 'API test show ' . bin2hex(random_bytes(4)), true);

        $controller = new ArticlesApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/articles/' . $article['slug']);
        $request->setRouteParams(['slug' => $article['slug']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame($article['slug'], $body['data']['slug']);
    }

    public function testShowReturns404ForDraftArticle(): void
    {
        $author = $this->createUser();
        $draft = $this->createArticle((int) $author['id'], 'API test hidden draft ' . bin2hex(random_bytes(4)), false);

        $controller = new ArticlesApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/articles/' . $draft['slug']);
        $request->setRouteParams(['slug' => $draft['slug']]);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }

    public function testShowReturns404ForUnknownSlug(): void
    {
        $controller = new ArticlesApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/articles/does-not-exist');
        $request->setRouteParams(['slug' => 'does-not-exist']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }
}

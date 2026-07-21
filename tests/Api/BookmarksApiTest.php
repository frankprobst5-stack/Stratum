<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\BookmarksApiController;
use Stratum\Modules\Articles\ArticleService;
use Tests\TestCase;

final class BookmarksApiTest extends TestCase
{
    /** @var int[] article ids created by this test, cleaned up in tearDown() */
    private array $articleIds = [];

    protected function tearDown(): void
    {
        foreach ($this->articleIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('bookmarks') . ' WHERE bookmarkable_type = :type AND bookmarkable_id = :id', ['type' => 'article', 'id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('articles_revisions') . ' WHERE article_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('articles') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->articleIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createArticle(): array
    {
        $author = $this->createUser();
        $service = new ArticleService($this->db);
        $result = $service->create([
            'category_id' => null,
            'author_id' => (int) $author['id'],
            'title' => 'API test bookmark article ' . bin2hex(random_bytes(4)),
            'excerpt' => '',
            'body' => 'body',
            'publish_action' => 'now',
        ]);
        $this->articleIds[] = (int) $result['articleId'];

        return $service->find((int) $result['articleId']);
    }

    public function testIndexRequiresAuthentication(): void
    {
        $controller = new BookmarksApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/bookmarks'));

        $this->assertSame(401, $response->status());
    }

    public function testToggleRequiresAuthentication(): void
    {
        $article = $this->createArticle();

        $controller = new BookmarksApiController($this->app);
        $request = $this->makeRequest('POST', '/api/v1/bookmarks/article/' . $article['id']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->toggle($request);

        $this->assertSame(401, $response->status());
    }

    public function testToggleRejectsUnknownType(): void
    {
        $user = $this->createUser();
        $app = $this->asUser($user);

        $controller = new BookmarksApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/bookmarks/bogus/1');
        $request->setRouteParams(['type' => 'bogus', 'id' => '1']);

        $response = $controller->toggle($request);

        $this->assertSame(422, $response->status());
    }

    public function testToggleRejectsUnresolvableTarget(): void
    {
        $user = $this->createUser();
        $app = $this->asUser($user);

        $controller = new BookmarksApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/bookmarks/article/999999999');
        $request->setRouteParams(['type' => 'article', 'id' => '999999999']);

        $response = $controller->toggle($request);

        $this->assertSame(404, $response->status());
    }

    public function testToggleAddsThenRemovesBookmarkAndIndexReflectsIt(): void
    {
        $article = $this->createArticle();
        $user = $this->createUser();
        $app = $this->asUser($user);

        $controller = new BookmarksApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/bookmarks/article/' . $article['id']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $addResponse = $controller->toggle($request);
        $addBody = json_decode($addResponse->body(), true);
        $this->assertSame(200, $addResponse->status());
        $this->assertTrue($addBody['data']['bookmarked']);

        $indexResponse = $controller->index($this->makeRequest('GET', '/api/v1/bookmarks'));
        $indexBody = json_decode($indexResponse->body(), true);
        $titles = array_column($indexBody['data'], 'title');
        $this->assertContains($article['title'], $titles);

        $removeResponse = $controller->toggle($request);
        $removeBody = json_decode($removeResponse->body(), true);
        $this->assertFalse($removeBody['data']['bookmarked']);
    }
}

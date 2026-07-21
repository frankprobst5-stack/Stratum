<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\CommentsApiController;
use Stratum\Modules\Articles\ArticleService;
use Stratum\Modules\Comments\CommentService;
use Tests\TestCase;

final class CommentsApiTest extends TestCase
{
    /** @var int[] article ids created by this test, cleaned up in tearDown() */
    private array $articleIds = [];

    protected function tearDown(): void
    {
        foreach ($this->articleIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('comments') . ' WHERE commentable_type = :type AND commentable_id = :id', ['type' => 'article', 'id' => $id]);
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
            'title' => 'API test comment article ' . bin2hex(random_bytes(4)),
            'excerpt' => '',
            'body' => 'body',
            'publish_action' => 'now',
        ]);
        $this->articleIds[] = (int) $result['articleId'];

        return $service->find((int) $result['articleId']);
    }

    public function testIndexListsExistingComment(): void
    {
        $article = $this->createArticle();
        $commenter = $this->createUser();
        (new CommentService($this->db))->create('article', (int) $article['id'], (int) $commenter['id'], 'a real comment');

        $controller = new CommentsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/comments/article/' . $article['id']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->index($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertCount(1, $body['data']);
        $this->assertSame('a real comment', $body['data'][0]['body']);
    }

    public function testIndexRejectsUnknownType(): void
    {
        $controller = new CommentsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/comments/bogus/1');
        $request->setRouteParams(['type' => 'bogus', 'id' => '1']);

        $response = $controller->index($request);

        $this->assertSame(422, $response->status());
    }

    public function testCreateRequiresAuthentication(): void
    {
        $article = $this->createArticle();

        $controller = new CommentsApiController($this->app);
        $request = $this->makeRequest('POST', '/api/v1/comments/article/' . $article['id'], body: ['body' => 'hello']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->create($request);

        $this->assertSame(401, $response->status());
    }

    public function testCreateForbiddenWithoutCapability(): void
    {
        $article = $this->createArticle();
        $commenter = $this->createUser();
        ['app' => $app, 'token' => $token] = $this->asApiUser($commenter);

        $controller = new CommentsApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/comments/article/' . $article['id'], body: ['body' => 'hello'], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->create($request);

        $this->assertSame(403, $response->status());
    }

    public function testCreateSucceedsWithCapability(): void
    {
        $article = $this->createArticle();
        $commenter = $this->createUser();
        $this->grantCapability((int) $commenter['id'], 'comments.create');
        ['app' => $app, 'token' => $token] = $this->asApiUser($commenter);

        $controller = new CommentsApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/comments/article/' . $article['id'], body: ['body' => 'a real reply'], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->create($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(201, $response->status());
        $this->assertSame('a real reply', $body['data']['body']);

        $stored = (new CommentService($this->db))->listFor('article', (int) $article['id']);
        $this->assertCount(1, $stored);
        $this->assertSame('a real reply', $stored[0]['body']);
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $article = $this->createArticle();
        $commenter = $this->createUser();
        $this->grantCapability((int) $commenter['id'], 'comments.create');
        ['app' => $app, 'token' => $token] = $this->asApiUser($commenter);

        $controller = new CommentsApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/comments/article/' . $article['id'], body: ['body' => '   '], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->create($request);

        $this->assertSame(422, $response->status());
    }
}

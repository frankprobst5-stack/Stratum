<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\ForumApiController;
use Stratum\Modules\Forum\ForumService;
use Tests\TestCase;

final class ForumApiTest extends TestCase
{
    /** @var int[] board ids created by this test, cleaned up (cascades to topics/posts) in tearDown() */
    private array $boardIds = [];

    protected function tearDown(): void
    {
        foreach ($this->boardIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('forum_posts') . '
                WHERE topic_id IN (SELECT id FROM ' . $this->db->table('forum_topics') . ' WHERE board_id = :id)', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('forum_topics') . ' WHERE board_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('forum_boards') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->boardIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createBoard(): array
    {
        $service = new ForumService($this->db);
        $categories = $service->listCategories();
        if ($categories === []) {
            $service->createCategory('API test category');
            $categories = $service->listCategories();
        }

        $name = 'API test board ' . bin2hex(random_bytes(4));
        $service->createBoard((int) $categories[0]['id'], $name, '');

        // createBoard() doesn't return an id and this test doesn't know its
        // own slug algorithm (Slug::make(), private to ForumService) — the
        // name is unique enough (random suffix) to find it back by name
        // rather than guessing the slug.
        $match = array_values(array_filter(
            $service->listBoards(),
            static fn (array $b): bool => $b['name'] === $name
        ));
        $board = $match[0];
        $this->boardIds[] = (int) $board['id'];

        return $board;
    }

    public function testBoardsListsExistingBoard(): void
    {
        $board = $this->createBoard();

        $controller = new ForumApiController($this->app);
        $response = $controller->boards($this->makeRequest('GET', '/api/v1/forum/boards'));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $ids = array_column($body['data'], 'id');
        $this->assertContains($board['id'], $ids);
    }

    public function testTopicsReturns404ForUnknownBoard(): void
    {
        $controller = new ForumApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/forum/boards/does-not-exist/topics');
        $request->setRouteParams(['slug' => 'does-not-exist']);

        $response = $controller->topics($request);

        $this->assertSame(404, $response->status());
    }

    public function testReplyRequiresAuthentication(): void
    {
        $board = $this->createBoard();
        $author = $this->createUser();
        $forum = new ForumService($this->db);
        $topic = $forum->createTopicWithFirstPost((int) $board['id'], (int) $author['id'], 'API test topic', 'first post');

        $controller = new ForumApiController($this->app);
        $request = $this->makeRequest('POST', '/api/v1/forum/topics/' . $topic['topicId'] . '/reply', body: ['body' => 'a reply']);
        $request->setRouteParams(['id' => (string) $topic['topicId']]);

        $response = $controller->reply($request);

        $this->assertSame(401, $response->status());
    }

    public function testReplyForbiddenWithoutCapability(): void
    {
        $board = $this->createBoard();
        $author = $this->createUser();
        $forum = new ForumService($this->db);
        $topic = $forum->createTopicWithFirstPost((int) $board['id'], (int) $author['id'], 'API test topic', 'first post');

        $replier = $this->createUser();
        $app = $this->asUser($replier);
        $controller = new ForumApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/forum/topics/' . $topic['topicId'] . '/reply', body: ['body' => 'a reply']);
        $request->setRouteParams(['id' => (string) $topic['topicId']]);

        $response = $controller->reply($request);

        $this->assertSame(403, $response->status());
    }

    public function testReplySucceedsWithCapability(): void
    {
        $board = $this->createBoard();
        $author = $this->createUser();
        $forum = new ForumService($this->db);
        $topic = $forum->createTopicWithFirstPost((int) $board['id'], (int) $author['id'], 'API test topic', 'first post');

        $replier = $this->createUser();
        $this->grantCapability((int) $replier['id'], 'forum.reply');
        $app = $this->asUser($replier);
        $controller = new ForumApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/forum/topics/' . $topic['topicId'] . '/reply', body: ['body' => 'a real reply']);
        $request->setRouteParams(['id' => (string) $topic['topicId']]);

        $response = $controller->reply($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(201, $response->status());
        $this->assertSame('a real reply', $body['data']['body']);
    }

    public function testReplyRejectedOnLockedTopic(): void
    {
        $board = $this->createBoard();
        $author = $this->createUser();
        $forum = new ForumService($this->db);
        $topic = $forum->createTopicWithFirstPost((int) $board['id'], (int) $author['id'], 'API test topic', 'first post');
        $forum->setLocked((int) $topic['topicId'], true);

        $replier = $this->createUser();
        $this->grantCapability((int) $replier['id'], 'forum.reply');
        $app = $this->asUser($replier);
        $controller = new ForumApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/forum/topics/' . $topic['topicId'] . '/reply', body: ['body' => 'a reply']);
        $request->setRouteParams(['id' => (string) $topic['topicId']]);

        $response = $controller->reply($request);

        $this->assertSame(403, $response->status());
    }

    public function testReplyRejectsEmptyBody(): void
    {
        $board = $this->createBoard();
        $author = $this->createUser();
        $forum = new ForumService($this->db);
        $topic = $forum->createTopicWithFirstPost((int) $board['id'], (int) $author['id'], 'API test topic', 'first post');

        $replier = $this->createUser();
        $this->grantCapability((int) $replier['id'], 'forum.reply');
        $app = $this->asUser($replier);
        $controller = new ForumApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/forum/topics/' . $topic['topicId'] . '/reply', body: ['body' => '   ']);
        $request->setRouteParams(['id' => (string) $topic['topicId']]);

        $response = $controller->reply($request);

        $this->assertSame(422, $response->status());
    }
}

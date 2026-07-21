<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\MessagesApiController;
use Stratum\Modules\Messages\MessagesService;
use Tests\TestCase;

final class MessagesApiTest extends TestCase
{
    /** @var int[] conversation ids created by this test, cleaned up in tearDown() */
    private array $conversationIds = [];

    protected function tearDown(): void
    {
        foreach ($this->conversationIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('direct_messages') . ' WHERE conversation_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('message_conversations') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->conversationIds = [];

        parent::tearDown();
    }

    public function testConversationsRequiresAuthentication(): void
    {
        $controller = new MessagesApiController($this->app);
        $response = $controller->conversations($this->makeRequest('GET', '/api/v1/messages/conversations'));

        $this->assertSame(401, $response->status());
    }

    public function testStartCreatesConversationAndSendsMessage(): void
    {
        $sender = $this->createUser();
        $recipient = $this->createUser();
        ['app' => $app, 'token' => $token] = $this->asApiUser($sender);

        $controller = new MessagesApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/messages/start', body: [
            'username' => $recipient['username'],
            'body' => 'hello there',
        ], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $response = $controller->start($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(201, $response->status());
        $this->conversationIds[] = (int) $body['data']['conversation_id'];

        $stored = (new MessagesService($this->db))->listMessagesInConversation((int) $body['data']['conversation_id']);
        $this->assertCount(1, $stored);
        $this->assertSame('hello there', $stored[0]['body']);
    }

    public function testStartRejectsUnknownUsername(): void
    {
        $sender = $this->createUser();
        ['app' => $app, 'token' => $token] = $this->asApiUser($sender);

        $controller = new MessagesApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/messages/start', body: [
            'username' => 'no-such-user-' . bin2hex(random_bytes(4)),
            'body' => 'hello',
        ], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $response = $controller->start($request);

        $this->assertSame(422, $response->status());
    }

    public function testStartRejectsMessagingSelf(): void
    {
        $sender = $this->createUser();
        ['app' => $app, 'token' => $token] = $this->asApiUser($sender);

        $controller = new MessagesApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/messages/start', body: [
            'username' => $sender['username'],
            'body' => 'hello me',
        ], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $response = $controller->start($request);

        $this->assertSame(422, $response->status());
    }

    public function testShowForbiddenForNonParticipant(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $outsider = $this->createUser();

        $messagesService = new MessagesService($this->db);
        $conversationId = $messagesService->findOrCreateConversation((int) $userA['id'], (int) $userB['id']);
        $this->conversationIds[] = $conversationId;

        ['app' => $app, 'token' => $token] = $this->asApiUser($outsider);
        $controller = new MessagesApiController($app);
        $request = $this->makeRequest('GET', '/api/v1/messages/conversations/' . $conversationId, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $request->setRouteParams(['id' => (string) $conversationId]);

        $response = $controller->show($request);

        $this->assertSame(403, $response->status());
    }

    public function testShowMarksConversationReadForParticipant(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $messagesService = new MessagesService($this->db);
        $conversationId = $messagesService->findOrCreateConversation((int) $userA['id'], (int) $userB['id']);
        $this->conversationIds[] = $conversationId;
        $messagesService->sendMessage($conversationId, (int) $userA['id'], 'unread message');

        $this->assertSame(1, $messagesService->unreadCount((int) $userB['id']));

        ['app' => $app, 'token' => $token] = $this->asApiUser($userB);
        $controller = new MessagesApiController($app);
        $request = $this->makeRequest('GET', '/api/v1/messages/conversations/' . $conversationId, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $request->setRouteParams(['id' => (string) $conversationId]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame($userA['username'], $body['data']['otherUsername']);
        $this->assertSame(0, $messagesService->unreadCount((int) $userB['id']));
    }

    public function testReplyForbiddenForNonParticipant(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $outsider = $this->createUser();

        $conversationId = (new MessagesService($this->db))->findOrCreateConversation((int) $userA['id'], (int) $userB['id']);
        $this->conversationIds[] = $conversationId;

        ['app' => $app, 'token' => $token] = $this->asApiUser($outsider);
        $controller = new MessagesApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/messages/conversations/' . $conversationId . '/reply', body: ['body' => 'sneaky'], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $request->setRouteParams(['id' => (string) $conversationId]);

        $response = $controller->reply($request);

        $this->assertSame(403, $response->status());
    }

    public function testReplySucceedsForParticipant(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $conversationId = (new MessagesService($this->db))->findOrCreateConversation((int) $userA['id'], (int) $userB['id']);
        $this->conversationIds[] = $conversationId;

        ['app' => $app, 'token' => $token] = $this->asApiUser($userB);
        $controller = new MessagesApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/messages/conversations/' . $conversationId . '/reply', body: ['body' => 'a real reply'], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $request->setRouteParams(['id' => (string) $conversationId]);

        $response = $controller->reply($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(201, $response->status());
        $this->assertSame('a real reply', $body['data']['body']);
    }

    public function testReplyRejectsEmptyBody(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $conversationId = (new MessagesService($this->db))->findOrCreateConversation((int) $userA['id'], (int) $userB['id']);
        $this->conversationIds[] = $conversationId;

        ['app' => $app, 'token' => $token] = $this->asApiUser($userB);
        $controller = new MessagesApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/messages/conversations/' . $conversationId . '/reply', body: ['body' => '  '], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $request->setRouteParams(['id' => (string) $conversationId]);

        $response = $controller->reply($request);

        $this->assertSame(422, $response->status());
    }
}

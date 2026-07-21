<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\ChatApiController;
use Stratum\Modules\Chat\ChatService;
use Tests\TestCase;

final class ChatApiTest extends TestCase
{
    /** @var int[] room ids created by this test, cleaned up (cascades to members/messages) in tearDown() */
    private array $roomIds = [];

    protected function tearDown(): void
    {
        foreach ($this->roomIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('chat_messages') . ' WHERE room_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('chat_room_members') . ' WHERE room_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('chat_rooms') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->roomIds = [];

        parent::tearDown();
    }

    /** @return array{id: int, name: string} */
    private function createPublicRoom(int $ownerUserId): array
    {
        $chat = new ChatService($this->db);
        $name = 'API test public room ' . bin2hex(random_bytes(4));
        $id = $chat->createUserRoom($name, null, $ownerUserId);
        $this->roomIds[] = (int) $id;

        return ['id' => (int) $id, 'name' => $name];
    }

    /** @return array{id: int, name: string} */
    private function createPrivateRoom(): array
    {
        $chat = new ChatService($this->db);
        $name = 'API test private room ' . bin2hex(random_bytes(4));
        $id = $chat->createAdminRoom($name, null, 'private');
        $this->roomIds[] = (int) $id;

        return ['id' => (int) $id, 'name' => $name];
    }

    public function testRoomsListsPublicRoom(): void
    {
        $owner = $this->createUser();
        $room = $this->createPublicRoom((int) $owner['id']);

        $controller = new ChatApiController($this->app);
        $response = $controller->rooms($this->makeRequest('GET', '/api/v1/chat/rooms', ['per_page' => '1000']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $names = array_column($body['data'], 'name');
        $this->assertContains($room['name'], $names);
    }

    public function testMessagesRequiresAuthentication(): void
    {
        $owner = $this->createUser();
        $room = $this->createPublicRoom((int) $owner['id']);

        $controller = new ChatApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/chat/rooms/' . $room['id'] . '/messages');
        $request->setRouteParams(['id' => (string) $room['id']]);

        $response = $controller->messages($request);

        $this->assertSame(401, $response->status());
    }

    public function testMessagesAutoJoinsPublicRoomOnView(): void
    {
        $owner = $this->createUser();
        $room = $this->createPublicRoom((int) $owner['id']);
        $viewer = $this->createUser();
        $app = $this->asUser($viewer);

        $chat = new ChatService($this->db);
        $this->assertFalse($chat->isMember($room['id'], (int) $viewer['id']));

        $controller = new ChatApiController($app);
        $request = $this->makeRequest('GET', '/api/v1/chat/rooms/' . $room['id'] . '/messages');
        $request->setRouteParams(['id' => (string) $room['id']]);

        $response = $controller->messages($request);

        $this->assertSame(200, $response->status());
        $this->assertTrue($chat->isMember($room['id'], (int) $viewer['id']));
    }

    public function testMessagesForbiddenForNonMemberOfPrivateRoom(): void
    {
        $room = $this->createPrivateRoom();
        $outsider = $this->createUser();
        $app = $this->asUser($outsider);

        $controller = new ChatApiController($app);
        $request = $this->makeRequest('GET', '/api/v1/chat/rooms/' . $room['id'] . '/messages');
        $request->setRouteParams(['id' => (string) $room['id']]);

        $response = $controller->messages($request);

        $this->assertSame(403, $response->status());
    }

    public function testMessagesSucceedsForPrivateRoomMember(): void
    {
        $room = $this->createPrivateRoom();
        $member = $this->createUser();
        (new ChatService($this->db))->joinRoom($room['id'], (int) $member['id']);
        $app = $this->asUser($member);

        $controller = new ChatApiController($app);
        $request = $this->makeRequest('GET', '/api/v1/chat/rooms/' . $room['id'] . '/messages');
        $request->setRouteParams(['id' => (string) $room['id']]);

        $response = $controller->messages($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame($room['name'], $body['data']['name']);
    }

    public function testPostMessageRequiresAuthentication(): void
    {
        $owner = $this->createUser();
        $room = $this->createPublicRoom((int) $owner['id']);

        $controller = new ChatApiController($this->app);
        $request = $this->makeRequest('POST', '/api/v1/chat/rooms/' . $room['id'] . '/messages', body: ['body' => 'hi']);
        $request->setRouteParams(['id' => (string) $room['id']]);

        $response = $controller->postMessage($request);

        $this->assertSame(401, $response->status());
    }

    public function testPostMessageForbiddenForNonMember(): void
    {
        $owner = $this->createUser();
        $room = $this->createPublicRoom((int) $owner['id']);
        $stranger = $this->createUser();
        $app = $this->asUser($stranger);

        $controller = new ChatApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/chat/rooms/' . $room['id'] . '/messages', body: ['body' => 'hi']);
        $request->setRouteParams(['id' => (string) $room['id']]);

        $response = $controller->postMessage($request);

        $this->assertSame(403, $response->status());
    }

    public function testPostMessageSucceedsForMember(): void
    {
        $owner = $this->createUser();
        $room = $this->createPublicRoom((int) $owner['id']);
        $app = $this->asUser($owner);

        $controller = new ChatApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/chat/rooms/' . $room['id'] . '/messages', body: ['body' => 'a real message']);
        $request->setRouteParams(['id' => (string) $room['id']]);

        $response = $controller->postMessage($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(201, $response->status());
        $this->assertSame('a real message', $body['data']['body']);
    }

    public function testPostMessageRejectsEmptyBody(): void
    {
        $owner = $this->createUser();
        $room = $this->createPublicRoom((int) $owner['id']);
        $app = $this->asUser($owner);

        $controller = new ChatApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/chat/rooms/' . $room['id'] . '/messages', body: ['body' => '   ']);
        $request->setRouteParams(['id' => (string) $room['id']]);

        $response = $controller->postMessage($request);

        $this->assertSame(422, $response->status());
    }
}

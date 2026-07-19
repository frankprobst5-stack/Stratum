<?php

declare(strict_types=1);

namespace Stratum\Modules\Chat;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class ChatController
{
    public function __construct(private readonly App $app)
    {
    }

    /** Guest-visible discovery page — same posture as the forum index (browse first, log in to participate). */
    public function index(Request $request): Response
    {
        $chat = new ChatService($this->app->db);

        $content = $this->app->templates->render('chat', 'index', [
            'rooms' => $chat->listPublicRooms(),
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createRoom(Request $request): Response
    {
        if (($guard = $this->requireLogin()) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $user = $this->app->auth->user();
        $chat = new ChatService($this->app->db);
        $id = $chat->createUserRoom(
            (string) $request->input('name', ''),
            $request->input('topic'),
            (int) $user['id']
        );

        return Response::redirect($id !== false ? '/chat/rooms/' . $id : '/chat');
    }

    /**
     * A public room is joined automatically just by viewing it — no
     * separate "Join" click needed, the lowest-friction reading of "user
     * rooms must be public" confirmed 2026-07-19. A private room has no
     * self-serve join at all; a non-member hitting this gets a 403.
     */
    public function room(Request $request): Response
    {
        if (($guard = $this->requireLogin()) !== null) {
            return $guard;
        }

        $chat = new ChatService($this->app->db);
        $room = $chat->findRoom((int) $request->param('id', '0'));
        if ($room === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $userId = (int) $user['id'];

        if ($room['visibility'] === 'private' && !$chat->isMember((int) $room['id'], $userId)) {
            return Response::forbidden();
        }

        if ($room['visibility'] === 'public') {
            $chat->joinRoom((int) $room['id'], $userId);
        }

        // Pre-rendered here, not inside room.php: a template's own $this
        // is bound to TemplateEngine (see TemplateEngine::capture()'s
        // closure), not App — it has no way to call back into
        // $this->app->templates->render() itself. Same reasoning
        // BlockPlacementsController::renderCard() already established.
        $messages = $chat->recentMessages((int) $room['id']);
        $messagesHtml = '';
        foreach ($messages as $message) {
            $messagesHtml .= $this->app->templates->render('chat', 'message', ['message' => $message]);
        }

        $content = $this->app->templates->render('chat', 'room', [
            'room' => $room,
            'messagesHtml' => $messagesHtml,
            'lastMessageId' => $messages !== [] ? (int) end($messages)['id'] : 0,
            'members' => $chat->listMembers((int) $room['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /** AJAX — posts a message (or a `/me` action), returns the rendered fragment so the client just appends a string, same pattern the block-management drag-and-drop endpoints established. */
    public function postMessage(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::json(['error' => 'invalid_csrf'], 400);
        }

        $chat = new ChatService($this->app->db);
        $roomId = (int) $request->param('id', '0');
        $room = $chat->findRoom($roomId);
        $user = $this->app->auth->user();

        if ($room === null || !$chat->isMember($roomId, (int) $user['id'])) {
            return Response::json(['error' => 'not_a_member'], 403);
        }

        $saved = $chat->postMessage($roomId, (int) $user['id'], (string) $request->input('body', ''));
        if ($saved === false) {
            return Response::json(['error' => 'empty_message'], 400);
        }

        return Response::json([
            'html' => $this->app->templates->render('chat', 'message', [
                'message' => $saved + ['username' => $user['username']],
            ]),
            'lastId' => $saved['id'],
        ]);
    }

    /** AJAX polling endpoint — everything after $afterId, rendered as ready-to-append HTML fragments. */
    public function pollMessages(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        $chat = new ChatService($this->app->db);
        $roomId = (int) $request->param('id', '0');
        $afterId = (int) $request->query('after', '0');

        $messages = $chat->messagesAfter($roomId, $afterId);
        $html = '';
        foreach ($messages as $message) {
            $html .= $this->app->templates->render('chat', 'message', ['message' => $message]);
        }
        $lastId = $messages !== [] ? (int) end($messages)['id'] : $afterId;

        return Response::json(['html' => $html, 'lastId' => $lastId]);
    }

    public function leaveRoom(Request $request): Response
    {
        if (($guard = $this->requireLogin()) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $chat = new ChatService($this->app->db);
        $roomId = (int) $request->param('id', '0');
        $user = $this->app->auth->user();
        $chat->leaveRoom($roomId, (int) $user['id']);

        // The room may no longer exist (self-deleted if it was the last
        // member of a user room) — redirecting to the room list either
        // way is always correct, never a dead link to a gone room.
        return Response::redirect('/chat');
    }

    /**
     * Invite is a notification nudge, not an access grant — confirmed
     * 2026-07-19, since every user room is public anyway, an "invite"
     * can't mean anything more than "hey, come look at this." Only
     * offered on public rooms; a private room's membership is admin-
     * managed, not something a member can extend themselves.
     */
    public function invite(Request $request): Response
    {
        if (($guard = $this->requireLogin()) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $chat = new ChatService($this->app->db);
        $roomId = (int) $request->param('id', '0');
        $room = $chat->findRoom($roomId);
        $user = $this->app->auth->user();

        if ($room === null || $room['visibility'] !== 'public') {
            return Response::redirect('/chat');
        }

        $username = trim((string) $request->input('username', ''));
        $target = $this->app->db->fetchOne(
            'SELECT id FROM ' . $this->app->db->table('users') . ' WHERE username = :username',
            ['username' => $username]
        );

        if ($target !== null && (int) $target['id'] !== (int) $user['id']) {
            $this->app->notify([
                'user_id' => (int) $target['id'],
                'actor_id' => (int) $user['id'],
                'type' => 'chat_invite',
                'message' => $user['username'] . ' invited you to chat room "' . $room['name'] . '"',
                'url' => '/chat/rooms/' . $roomId,
            ]);
        }

        return Response::redirect('/chat/rooms/' . $roomId);
    }

    private function requireLogin(): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        return null;
    }
}

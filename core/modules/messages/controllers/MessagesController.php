<?php

declare(strict_types=1);

namespace Stratum\Modules\Messages;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class MessagesController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $service = new MessagesService($this->app->db);
        $user = $this->app->auth->user();

        $content = $this->app->templates->render('messages', 'index', [
            'conversations' => $service->listConversationsForUser((int) $user['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function start(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $username = trim((string) $request->input('username', ''));
        $body = trim((string) $request->input('body', ''));
        if ($username === '' || $body === '') {
            return Response::redirect('/messages');
        }

        $recipient = (new AuthService($this->app->db))->findByUsername($username);
        if ($recipient === null) {
            return Response::html('No user with that username.', 422);
        }

        $user = $this->app->auth->user();
        $recipientId = (int) $recipient['id'];
        if ($recipientId === (int) $user['id']) {
            return Response::html("You can't message yourself.", 422);
        }

        $service = new MessagesService($this->app->db);
        $conversationId = $service->findOrCreateConversation((int) $user['id'], $recipientId);
        $service->sendMessage($conversationId, (int) $user['id'], $body);

        $this->app->notify([
            'user_id' => $recipientId,
            'actor_id' => (int) $user['id'],
            'type' => 'message.received',
            'message' => (string) $user['username'] . ' sent you a message.',
            'url' => '/messages/' . $conversationId,
        ]);

        return Response::redirect('/messages/' . $conversationId);
    }

    public function conversation(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $service = new MessagesService($this->app->db);
        $conversation = $service->findConversation((int) $request->param('id', '0'));
        if ($conversation === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        if (!$service->isParticipant($conversation, (int) $user['id'])) {
            return Response::forbidden();
        }

        $service->markConversationRead((int) $conversation['id'], (int) $user['id']);

        $authors = new AuthService($this->app->db);
        $other = $authors->findById($service->otherParticipantId($conversation, (int) $user['id']));

        $content = $this->app->templates->render('messages', 'conversation', [
            'conversation' => $conversation,
            'otherUsername' => $other['username'] ?? 'Unknown',
            'messages' => $service->listMessagesInConversation((int) $conversation['id']),
            'currentUserId' => (int) $user['id'],
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function reply(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new MessagesService($this->app->db);
        $conversationId = (int) $request->param('id', '0');
        $conversation = $service->findConversation($conversationId);
        if ($conversation === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        if (!$service->isParticipant($conversation, (int) $user['id'])) {
            return Response::forbidden();
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return Response::redirect('/messages/' . $conversationId);
        }

        $service->sendMessage($conversationId, (int) $user['id'], $body);

        $this->app->notify([
            'user_id' => $service->otherParticipantId($conversation, (int) $user['id']),
            'actor_id' => (int) $user['id'],
            'type' => 'message.received',
            'message' => (string) $user['username'] . ' sent you a message.',
            'url' => '/messages/' . $conversationId,
        ]);

        return Response::redirect('/messages/' . $conversationId);
    }
}

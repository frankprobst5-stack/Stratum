<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Messages\MessagesService;
use Stratum\Modules\Users\AuthService;

/**
 * A fully private resource — every action here is scoped to the caller's
 * own conversations, mirroring MessagesController's exact login+participant
 * checks. Unlike bookmarks (the other private resource in this API),
 * messages has real content on both sides (sender and recipient), so every
 * action here needs the same `isParticipant()` guard the web controller
 * already applies before returning or accepting anything.
 */
final class MessagesApiController extends ApiController
{
    public function conversations(Request $request): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $pagination = $this->paginationParams($request);
        $userId = (int) $this->app->auth->user()['id'];
        $all = (new MessagesService($this->app->db))->listConversationsForUser($userId);

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    /** Mirrors MessagesController::conversation() exactly, including marking it read as a side effect of viewing — same reasoning chat's auto-join has for staying in a GET. */
    public function show(Request $request): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $messages = new MessagesService($this->app->db);
        $conversationId = (int) $request->param('id', '0');
        $conversation = $messages->findConversation($conversationId);
        if ($conversation === null) {
            return ApiResponse::notFound();
        }

        $userId = (int) $this->app->auth->user()['id'];
        if (!$messages->isParticipant($conversation, $userId)) {
            return ApiResponse::forbidden();
        }

        $messages->markConversationRead($conversationId, $userId);

        $other = (new AuthService($this->app->db))->findById($messages->otherParticipantId($conversation, $userId));

        return ApiResponse::data($conversation + [
            'otherUsername' => $other['username'] ?? 'Unknown',
            'messages' => $messages->listMessagesInConversation($conversationId),
        ]);
    }

    /** Mirrors MessagesController::reply() exactly (participant check, empty-body rejection, same notify()). */
    public function reply(Request $request): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $messages = new MessagesService($this->app->db);
        $conversationId = (int) $request->param('id', '0');
        $conversation = $messages->findConversation($conversationId);
        if ($conversation === null) {
            return ApiResponse::notFound();
        }

        $user = $this->app->auth->user();
        $userId = (int) $user['id'];
        if (!$messages->isParticipant($conversation, $userId)) {
            return ApiResponse::forbidden();
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return ApiResponse::error('A message body is required.', 422, 'validation_failed');
        }

        $messages->sendMessage($conversationId, $userId, $body);

        $this->app->notify([
            'user_id' => $messages->otherParticipantId($conversation, $userId),
            'actor_id' => $userId,
            'type' => 'message.received',
            'message' => (string) $user['username'] . ' sent you a message.',
            'url' => '/messages/' . $conversationId,
        ]);

        return ApiResponse::data(['conversation_id' => $conversationId, 'body' => $body], 201);
    }

    /** Mirrors MessagesController::start() exactly (username resolution, self-message rejection, same notify()). */
    public function start(Request $request): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $username = trim((string) $request->input('username', ''));
        $body = trim((string) $request->input('body', ''));
        if ($username === '' || $body === '') {
            return ApiResponse::error('username and body are required.', 422, 'validation_failed');
        }

        $recipient = (new AuthService($this->app->db))->findByUsername($username);
        if ($recipient === null) {
            return ApiResponse::error('No user with that username.', 422, 'unknown_recipient');
        }

        $user = $this->app->auth->user();
        $recipientId = (int) $recipient['id'];
        if ($recipientId === (int) $user['id']) {
            return ApiResponse::error("You can't message yourself.", 422, 'validation_failed');
        }

        $messages = new MessagesService($this->app->db);
        $conversationId = $messages->findOrCreateConversation((int) $user['id'], $recipientId);
        $messages->sendMessage($conversationId, (int) $user['id'], $body);

        $this->app->notify([
            'user_id' => $recipientId,
            'actor_id' => (int) $user['id'],
            'type' => 'message.received',
            'message' => (string) $user['username'] . ' sent you a message.',
            'url' => '/messages/' . $conversationId,
        ]);

        return ApiResponse::data(['conversation_id' => $conversationId, 'body' => $body], 201);
    }
}

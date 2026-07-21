<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Chat\ChatService;

final class ChatApiController extends ApiController
{
    /** Room discovery is public — mirrors ChatController::index()'s own "browse first, log in to participate" posture. */
    public function rooms(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        $all = (new ChatService($this->app->db))->listPublicRooms(100000);

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    /**
     * Mirrors ChatController::room()'s exact access logic — a Bearer
     * token is required to view messages at all (chat, unlike every other
     * read endpoint in this API, isn't guest-visible), a private room
     * 403s a non-member, and viewing a public room auto-joins the caller
     * just like the web page does — a real side effect on a GET, not an
     * API-only shortcut.
     */
    public function messages(Request $request): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $chat = new ChatService($this->app->db);
        $roomId = (int) $request->param('id', '0');
        $room = $chat->findRoom($roomId);
        if ($room === null) {
            return ApiResponse::notFound();
        }

        $userId = (int) $this->app->auth->user()['id'];
        if ($room['visibility'] === 'private' && !$chat->isMember($roomId, $userId)) {
            return ApiResponse::forbidden();
        }

        if ($room['visibility'] === 'public') {
            $chat->joinRoom($roomId, $userId);
        }

        return ApiResponse::data($room + ['messages' => $chat->recentMessages($roomId)]);
    }

    /**
     * The one write endpoint in this slice — mirrors
     * ChatController::postMessage()'s exact membership check and empty-
     * message rejection.
     */
    public function postMessage(Request $request): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $chat = new ChatService($this->app->db);
        $roomId = (int) $request->param('id', '0');
        $room = $chat->findRoom($roomId);
        $user = $this->app->auth->user();

        if ($room === null || !$chat->isMember($roomId, (int) $user['id'])) {
            return ApiResponse::forbidden();
        }

        $saved = $chat->postMessage($roomId, (int) $user['id'], (string) $request->input('body', ''));
        if ($saved === false) {
            return ApiResponse::error('A message body is required.', 422, 'validation_failed');
        }

        return ApiResponse::data($saved + ['username' => $user['username']], 201);
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Forum\ForumService;

final class ForumApiController extends ApiController
{
    /** Public reads — no auth required, same access model the web /forum routes already have. */
    public function boards(Request $request): Response
    {
        return ApiResponse::data((new ForumService($this->app->db))->listBoards());
    }

    public function topics(Request $request): Response
    {
        $forum = new ForumService($this->app->db);
        $board = $forum->findBoardBySlug((string) $request->param('slug', ''));
        if ($board === null) {
            return ApiResponse::notFound();
        }

        $pagination = $this->paginationParams($request);
        $all = $forum->listTopicsForBoard((int) $board['id']);

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function topic(Request $request): Response
    {
        $forum = new ForumService($this->app->db);
        $topic = $forum->findTopic((int) $request->param('id', '0'));
        if ($topic === null) {
            return ApiResponse::notFound();
        }

        return ApiResponse::data($topic + ['posts' => $forum->listPostsForTopic((int) $topic['id'])]);
    }

    /**
     * The one write endpoint in this slice — deliberately mirrors
     * ForumController::reply() exactly (same capability, same locked-topic
     * check, same notify() call), just Bearer-authed and JSON-shaped
     * instead of session+CSRF and a redirect. No attachment support here
     * (out of scope for this slice, per the plan — text replies only).
     */
    public function reply(Request $request): Response
    {
        if (($guard = $this->guard($request, 'forum.reply')) !== null) {
            return $guard;
        }

        $forum = new ForumService($this->app->db);
        $topicId = (int) $request->param('id', '0');
        $topic = $forum->findTopic($topicId);
        if ($topic === null) {
            return ApiResponse::notFound();
        }

        if ((bool) $topic['is_locked'] && !$this->app->auth->can('forum.moderate', 'forum_board', (int) $topic['board_id'])) {
            return ApiResponse::error('This topic is locked.', 403, 'topic_locked');
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return ApiResponse::error('A reply body is required.', 422, 'validation_failed');
        }

        $user = $this->app->auth->user();
        $postId = $forum->reply($topicId, (int) $user['id'], $body);

        $this->app->notify([
            'user_id' => (int) $topic['author_id'],
            'actor_id' => (int) $user['id'],
            'type' => 'forum.reply',
            'message' => (string) $user['username'] . ' replied to "' . $topic['title'] . '"',
            'url' => '/forum/topics/' . $topicId,
        ]);

        return ApiResponse::data($forum->findPost($postId), 201);
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Comments\CommentService;

final class CommentsApiController extends ApiController
{
    /** @var array<int, string> only these commentable_type values are accepted — same allowlist CommentsController::resolveOwner() recognizes, plus wiki_page (ownerless, no notify) */
    private const ALLOWED_TYPES = ['article', 'video', 'gallery_photo', 'calendar_event', 'wiki_page'];

    /** Public reads — no auth required, same access model the web content pages already have. */
    public function index(Request $request): Response
    {
        $type = (string) $request->param('type', '');
        $id = (int) $request->param('id', '0');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ApiResponse::error('Unknown commentable_type.', 422, 'invalid_type');
        }

        $pagination = $this->paginationParams($request);
        $all = (new CommentService($this->app->db))->listFor($type, $id);

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    /**
     * The one write endpoint in this slice — mirrors
     * CommentsController::create() exactly (same capability, same owner
     * notify()), just Bearer-authed and JSON-shaped instead of session+CSRF
     * and a redirect.
     */
    public function create(Request $request): Response
    {
        if (($guard = $this->guard($request, 'comments.create')) !== null) {
            return $guard;
        }

        $type = (string) $request->param('type', '');
        $id = (int) $request->param('id', '0');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ApiResponse::error('Unknown commentable_type.', 422, 'invalid_type');
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return ApiResponse::error('A comment body is required.', 422, 'validation_failed');
        }

        $user = $this->app->auth->user();
        (new CommentService($this->app->db))->create($type, $id, (int) $user['id'], $body);

        // Self-comments are skipped inside the notify listener, not here —
        // matches CommentsController::create()'s exact behavior.
        [$ownerId, $label] = $this->resolveOwner($type, $id);
        if ($ownerId !== null) {
            $this->app->notify([
                'user_id' => $ownerId,
                'actor_id' => (int) $user['id'],
                'type' => 'comment',
                'message' => (string) $user['username'] . ' commented on your ' . $label,
                'url' => null,
            ]);
        }

        return ApiResponse::data([
            'commentable_type' => $type,
            'commentable_id' => $id,
            'body' => $body,
        ], 201);
    }

    /** Same owner-resolution map as CommentsController — see that class's docblock for why wiki_page has no owner column. @return array{0: ?int, 1: string} */
    private function resolveOwner(string $type, int $id): array
    {
        $map = [
            'article' => ['articles', 'author_id', 'article'],
            'video' => ['videos', 'uploader_id', 'video'],
            'gallery_photo' => ['gallery_photos', 'uploader_id', 'photo'],
            'calendar_event' => ['calendar_events', 'author_id', 'event'],
        ];

        if (!isset($map[$type])) {
            return [null, ''];
        }

        [$table, $ownerColumn, $label] = $map[$type];
        $row = $this->app->db->fetchOne(
            "SELECT {$ownerColumn} AS owner_id FROM " . $this->app->db->table($table) . ' WHERE id = :id',
            ['id' => $id]
        );

        $ownerId = $row['owner_id'] ?? null;

        return [$ownerId !== null ? (int) $ownerId : null, $label];
    }
}

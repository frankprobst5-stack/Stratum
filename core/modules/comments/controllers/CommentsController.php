<?php

declare(strict_types=1);

namespace Stratum\Modules\Comments;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class CommentsController
{
    public function __construct(private readonly App $app)
    {
    }

    public function create(Request $request): Response
    {
        $redirectTo = $this->safeRedirectTarget($request->input('redirect_to', '/'));

        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can('comments.create')) {
            return Response::forbidden();
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $type = (string) $request->input('commentable_type', '');
        $id = (int) $request->input('commentable_id', '0');
        $body = trim((string) $request->input('body', ''));

        if ($type !== '' && $id > 0 && $body !== '') {
            $user = $this->app->auth->user();
            $service = new CommentService($this->app->db);
            $service->create($type, $id, (int) $user['id'], $body);

            [$ownerId, $label] = $this->resolveOwner($type, $id);
            if ($ownerId !== null) {
                // $redirectTo is already sanitized to a local path above, so
                // it doubles as the notification link — no per-type URL
                // building needed. Self-comments are skipped in the listener.
                $this->app->notify([
                    'user_id' => $ownerId,
                    'actor_id' => (int) $user['id'],
                    'type' => 'comment',
                    'message' => $user['username'] . ' commented on your ' . $label,
                    'url' => $redirectTo,
                ]);
            }
        }

        return Response::redirect($redirectTo);
    }

    /**
     * Maps a commentable_type to its owner user id, for notifying the
     * content's owner about a new comment. wiki_page is deliberately absent:
     * wiki pages are communal content with no owner column ("first revision
     * author owns it" would be wrong more often than right). Video/gallery
     * uploader ids are nullable (ON DELETE SET NULL) — a null owner simply
     * means no notification.
     *
     * @return array{0: ?int, 1: string} [ownerId, human label]
     */
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

    /** Only ever redirect to a local path — never trust the client-supplied target as-is. */
    private function safeRedirectTarget(?string $path): string
    {
        if ($path === null || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        return $path;
    }
}

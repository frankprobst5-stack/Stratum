<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\ContentResolver;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Bookmarks\BookmarkService;

final class BookmarksApiController extends ApiController
{
    /**
     * A private resource, unlike everything else in this API so far — a
     * Bearer token is required and the response is always scoped to the
     * caller's own bookmarks. There's no capability check beyond being
     * logged in, matching BookmarkController's own posture: bookmarking is
     * private and non-content-creating, no moderation surface.
     */
    public function index(Request $request): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $pagination = $this->paginationParams($request);
        $userId = (int) $this->app->auth->user()['id'];
        $all = (new BookmarkService($this->app->db))->listForUser($userId);

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    /**
     * The one write endpoint in this slice — mirrors
     * BookmarkController::toggle() exactly (same bookmarkable-type
     * allowlist, same "confirm the target still resolves before writing a
     * row" guard), just Bearer-authed and JSON-shaped instead of
     * session+CSRF and a redirect.
     */
    public function toggle(Request $request): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $type = (string) $request->param('type', '');
        $id = (int) $request->param('id', '0');

        $bookmarks = new BookmarkService($this->app->db);
        if (!$bookmarks->isBookmarkable($type)) {
            return ApiResponse::error('Unknown bookmarkable_type.', 422, 'invalid_type');
        }

        if ((new ContentResolver($this->app->db))->resolve($type, $id) === null) {
            return ApiResponse::notFound();
        }

        $userId = (int) $this->app->auth->user()['id'];
        $bookmarked = $bookmarks->toggle($type, $id, $userId);

        return ApiResponse::data(['bookmarked' => $bookmarked]);
    }
}

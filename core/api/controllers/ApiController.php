<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\ApiTokenService;
use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

/**
 * Base for every /api/v1/ controller — mirrors AdminController's shape
 * (core/admin/controllers/AdminController.php) exactly, just returning a
 * JSON error envelope instead of a redirect/HTML 403.
 */
abstract class ApiController
{
    public function __construct(protected readonly App $app)
    {
    }

    /**
     * Call at the top of any action that requires a real authenticated
     * user. Deliberately requires an actual, *valid* Bearer token on
     * *this* request — resolved here directly via ApiTokenService, not
     * via Auth::check()/user(), which also honors an ambient browser
     * session. That session fallback is what every web controller relies
     * on, but it has no CSRF protection at this layer (unlike every web
     * *Controller.php, which calls Session::verifyCsrf() on every
     * mutation) — so a logged-in member's browser session alone used to
     * be enough to drive any API write endpoint from a third-party page,
     * and a garbage/expired Bearer header would have silently fallen back
     * to that same session. Resolving the token independently here closes
     * both: no Bearer token at all, or one that doesn't resolve, is
     * always a hard 401, never a fallback. Found and fixed 2026-07-20 —
     * see docs/roadmap.md's Stage 10 security-audit entry.
     */
    protected function guard(Request $request, ?string $capability = null): ?Response
    {
        $header = $request->server('HTTP_AUTHORIZATION') ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return ApiResponse::unauthenticated();
        }

        $rawToken = trim(substr($header, 7));
        if ($rawToken === '' || (new ApiTokenService($this->app->db))->resolveUserIdFromToken($rawToken) === null) {
            return ApiResponse::unauthenticated();
        }

        if (!$this->app->auth->check()) {
            return ApiResponse::unauthenticated();
        }

        if ($capability !== null && !$this->app->auth->can($capability)) {
            return ApiResponse::forbidden();
        }

        return null;
    }

    /** @return array{page: int, perPage: int, offset: int} */
    protected function paginationParams(Request $request): array
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(100, max(1, (int) $request->query('per_page', '20')));

        return ['page' => $page, 'perPage' => $perPage, 'offset' => ($page - 1) * $perPage];
    }
}

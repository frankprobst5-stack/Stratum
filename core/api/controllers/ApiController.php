<?php

declare(strict_types=1);

namespace Stratum\Api;

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

    /** Call at the top of any action that requires a real authenticated user (session or Bearer token — Auth::user() already resolves either). */
    protected function guard(?string $capability = null): ?Response
    {
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

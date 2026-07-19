<?php

declare(strict_types=1);

namespace Stratum\Modules\Ratings;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class RatingsController
{
    /** @var array<int, string> only these ratable_type values are accepted — never trust the client's string as-is */
    private const ALLOWED_TYPES = ['article', 'download'];

    public function __construct(private readonly App $app)
    {
    }

    public function rate(Request $request): Response
    {
        $redirectTo = $this->safeRedirectTarget($request->input('redirect_to', '/'));

        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can('ratings.create')) {
            return Response::forbidden();
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $type = (string) $request->input('ratable_type', '');
        $id = (int) $request->input('ratable_id', '0');
        $score = (int) $request->input('score', '0');

        if (in_array($type, self::ALLOWED_TYPES, true) && $id > 0 && $score >= 1 && $score <= 5) {
            $user = $this->app->auth->user();
            (new RatingService($this->app->db))->rate($type, $id, (int) $user['id'], $score);
        }

        return Response::redirect($redirectTo);
    }

    /** Only ever redirect to a local path — never trust the client-supplied target as-is. Same guard CommentsController uses. */
    private function safeRedirectTarget(?string $path): string
    {
        if ($path === null || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        return $path;
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Ratings\RatingService;

final class RatingsApiController extends ApiController
{
    /** @var array<int, string> only these ratable_type values are accepted — same allowlist RatingsController uses */
    private const ALLOWED_TYPES = ['article', 'download'];

    /** Public — no auth required, same access model the web article/download pages already have. */
    public function show(Request $request): Response
    {
        $type = (string) $request->param('type', '');
        $id = (int) $request->param('id', '0');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ApiResponse::error('Unknown ratable_type.', 422, 'invalid_type');
        }

        $ratings = new RatingService($this->app->db);
        $summary = $ratings->summaryFor($type, $id);

        $myRating = null;
        if ($this->app->auth->check()) {
            $myRating = $ratings->myRating($type, $id, (int) $this->app->auth->user()['id']);
        }

        return ApiResponse::data($summary + ['myRating' => $myRating]);
    }

    /**
     * The one write endpoint in this slice — mirrors
     * RatingsController::rate() exactly (same capability, same allowed
     * types, same 1-5 score clamp), just Bearer-authed and JSON-shaped
     * instead of session+CSRF and a redirect.
     */
    public function rate(Request $request): Response
    {
        if (($guard = $this->guard($request, 'ratings.create')) !== null) {
            return $guard;
        }

        $type = (string) $request->param('type', '');
        $id = (int) $request->param('id', '0');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ApiResponse::error('Unknown ratable_type.', 422, 'invalid_type');
        }

        $score = (int) $request->input('score', '0');
        if ($score < 1 || $score > 5) {
            return ApiResponse::error('score must be between 1 and 5.', 422, 'validation_failed');
        }

        $user = $this->app->auth->user();
        $ratings = new RatingService($this->app->db);
        $ratings->rate($type, $id, (int) $user['id'], $score);

        return ApiResponse::data($ratings->summaryFor($type, $id) + ['myRating' => $score]);
    }
}

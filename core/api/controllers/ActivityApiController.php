<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Activity\ActivityService;

final class ActivityApiController extends ApiController
{
    /**
     * Public — no auth required, same access model the site-wide activity
     * feed already has. Not paginated: ActivityService::recent() already
     * caps itself at a fixed 40 most-recent items across every enabled
     * content module, the same shape the web feature itself uses — this
     * is a live snapshot, not a browsable archive.
     */
    public function index(Request $request): Response
    {
        return ApiResponse::data((new ActivityService($this->app->db, $this->app->modules))->recent());
    }
}

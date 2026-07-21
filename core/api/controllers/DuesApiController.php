<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Dues\DuesService;

/**
 * Reads only — deliberately, confirmed 2026-07-20, same reasoning as
 * CommerceApiController: dues touches real money (Cash App, manual admin
 * confirm), so paying stays web-only through the existing flow.
 */
final class DuesApiController extends ApiController
{
    /** Public — no auth required, same access model the web /dues route already has. */
    public function index(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        $all = (new DuesService($this->app->db, $this->app->permissions))->listPlans();

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function show(Request $request): Response
    {
        $dues = new DuesService($this->app->db, $this->app->permissions);
        $plan = $dues->findPlan((int) $request->param('id', '0'));
        if ($plan === null) {
            return ApiResponse::notFound();
        }

        $isCurrent = null;
        if ($this->app->auth->check()) {
            $isCurrent = $dues->isCurrentOnPlan((int) $this->app->auth->user()['id'], (int) $plan['id']);
        }

        return ApiResponse::data($plan + ['isCurrent' => $isCurrent]);
    }
}

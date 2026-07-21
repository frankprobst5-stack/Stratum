<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Donations\DonationService;

/**
 * Reads only — deliberately, confirmed 2026-07-20, same reasoning as
 * CommerceApiController: donations touches real money (Cash App, manual
 * admin confirm), so contributing stays web-only through the existing flow.
 */
final class DonationsApiController extends ApiController
{
    /** Public — no auth required, same access model the web /donations route already has. */
    public function index(Request $request): Response
    {
        $donations = new DonationService($this->app->db);
        $pagination = $this->paginationParams($request);
        $all = array_map(
            fn (array $c): array => $c + ['raised' => $donations->raisedAmount((int) $c['id'])],
            $donations->listCampaigns()
        );

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function show(Request $request): Response
    {
        $donations = new DonationService($this->app->db);
        $campaign = $donations->findCampaign((int) $request->param('id', '0'));
        if ($campaign === null) {
            return ApiResponse::notFound();
        }

        return ApiResponse::data($campaign + ['raised' => $donations->raisedAmount((int) $campaign['id'])]);
    }
}

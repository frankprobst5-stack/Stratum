<?php

declare(strict_types=1);

namespace Stratum\Modules\Sponsors;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class SponsorsController
{
    public function __construct(private readonly App $app)
    {
    }

    /** Tracks a click, then redirects to the sponsor's URL — same "count then redirect" shape links/affiliates/ads all use. */
    public function click(Request $request): Response
    {
        $service = new SponsorService($this->app->db);
        $sponsor = $service->find((int) $request->param('id', '0'));
        if ($sponsor === null) {
            return Response::notFound();
        }

        $service->incrementClickCount((int) $sponsor['id']);

        return Response::redirect($sponsor['link_url']);
    }
}

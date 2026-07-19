<?php

declare(strict_types=1);

namespace Stratum\Modules\Ads;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class AdsController
{
    public function __construct(private readonly App $app)
    {
    }

    /** Tracks a click, then redirects to the advertiser's link — same "count then redirect" shape links' visit() action uses for click_count. */
    public function click(Request $request): Response
    {
        $service = new AdService($this->app->db);
        $banner = $service->findBanner((int) $request->param('id', '0'));
        if ($banner === null) {
            return Response::notFound();
        }

        $service->incrementClickCount((int) $banner['id']);

        return Response::redirect($banner['link_url']);
    }
}

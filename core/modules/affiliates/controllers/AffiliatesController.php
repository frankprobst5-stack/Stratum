<?php

declare(strict_types=1);

namespace Stratum\Modules\Affiliates;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class AffiliatesController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new AffiliateService($this->app->db);

        $content = $this->app->templates->render('affiliates', 'index', [
            'links' => $service->listActive(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /** Tracks a click, then redirects to the partner's URL — same "count then redirect" shape Link Directory's visit() action uses. */
    public function visit(Request $request): Response
    {
        $service = new AffiliateService($this->app->db);
        $link = $service->find((int) $request->param('id', '0'));
        if ($link === null) {
            return Response::notFound();
        }

        $service->incrementClickCount((int) $link['id']);

        return Response::redirect($link['url']);
    }
}

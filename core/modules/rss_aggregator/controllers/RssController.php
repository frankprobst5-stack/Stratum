<?php

declare(strict_types=1);

namespace Stratum\Modules\RssAggregator;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class RssController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $items = (new RssSourceService($this->app->db))->listRecentItems();

        $content = $this->app->templates->render('rss_aggregator', 'index', [
            'items' => $items,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function feedXml(Request $request): Response
    {
        $xml = (new ArticleFeedExporter($this->app->db))->buildXml($request->baseUrl());

        return Response::xml($xml);
    }
}

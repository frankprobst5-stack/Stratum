<?php

declare(strict_types=1);

namespace Stratum\Modules\Search;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class SearchController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $results = $query !== ''
            ? (new SearchService($this->app->db, $this->app->modules))->search($query)
            : [];

        $content = $this->app->templates->render('search', 'index', [
            'query' => $query,
            'results' => $results,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}

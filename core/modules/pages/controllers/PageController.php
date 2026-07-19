<?php

declare(strict_types=1);

namespace Stratum\Modules\Pages;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\SeoService;

final class PageController
{
    public function __construct(private readonly App $app)
    {
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->param('slug', '');
        $page = (new PageService($this->app->db))->findPublishedBySlug($slug);

        if ($page === null) {
            return Response::notFound();
        }

        $content = $this->app->templates->render('pages', 'show', ['page' => $page]);

        $seo = [
            'title' => $page['title'],
            'description' => (new SeoService())->excerpt((string) $page['body']),
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }
}

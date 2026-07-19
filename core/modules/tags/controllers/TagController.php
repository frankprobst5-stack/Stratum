<?php

declare(strict_types=1);

namespace Stratum\Modules\Tags;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class TagController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new TagService($this->app->db);

        $content = $this->app->templates->render('tags', 'index', [
            'tags' => $service->popularTags(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function show(Request $request): Response
    {
        $service = new TagService($this->app->db);
        $tag = $service->findBySlug((string) $request->param('slug', ''));
        if ($tag === null) {
            return Response::notFound();
        }

        $content = $this->app->templates->render('tags', 'show', [
            'tag' => $tag,
            'items' => $service->contentForTag((int) $tag['id']),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}

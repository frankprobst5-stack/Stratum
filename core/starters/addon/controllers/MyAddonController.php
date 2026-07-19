<?php

declare(strict_types=1);

namespace Stratum\Modules\MyAddon;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

/**
 * A minimal working controller — every real addon controller in this
 * app follows this exact shape: a constructor taking $app, one action
 * per public route, rendering a template via $app->templates->render(),
 * wrapped in $app->renderPage() so it gets the site's normal header/nav/
 * footer (and, on /admin/... routes, the admin chrome instead —
 * App::renderPage() decides that automatically by URL path).
 */
final class MyAddonController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $content = $this->app->templates->render('my_addon', 'index', [
            'message' => 'Hello from My Addon!',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}

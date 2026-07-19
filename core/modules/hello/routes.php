<?php

declare(strict_types=1);

use Stratum\Core\Request;
use Stratum\Core\Response;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$router->get('/hello', function (Request $request) use ($app): Response {
    $content = $app->templates->render('hello', 'index', []);

    return Response::html($app->renderPage($content, $request));
});

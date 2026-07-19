<?php

declare(strict_types=1);

use Stratum\Core\Request;
use Stratum\Core\Response;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$router->get('/messages', function (Request $request) use ($app): Response {
    if (!$app->auth->check()) {
        return Response::redirect('/login');
    }

    $content = $app->templates->render('messages', 'index', []);

    return Response::html($app->renderPage($content, $request));
});

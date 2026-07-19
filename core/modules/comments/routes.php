<?php

declare(strict_types=1);

use Stratum\Modules\Comments\CommentsController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$controller = new CommentsController($app);

$router->post('/comments', [$controller, 'create']);

<?php

declare(strict_types=1);

use Stratum\Modules\Activity\ActivityController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$activity = new ActivityController($app);

$router->get('/activity', [$activity, 'index']);

<?php

declare(strict_types=1);

use Stratum\Modules\Ratings\RatingsController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$controller = new RatingsController($app);

$router->post('/ratings', [$controller, 'rate']);

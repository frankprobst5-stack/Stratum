<?php

declare(strict_types=1);

use Stratum\Modules\Search\SearchController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$search = new SearchController($app);

$router->get('/search', [$search, 'index']);

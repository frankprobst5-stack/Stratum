<?php

declare(strict_types=1);

use Stratum\Modules\MyAddon\MyAddonController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$controller = new MyAddonController($app);

$router->get('/my-addon', [$controller, 'index']);

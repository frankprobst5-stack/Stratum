<?php

declare(strict_types=1);

use Stratum\Modules\Sponsors\SponsorsAdminController;
use Stratum\Modules\Sponsors\SponsorsController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$sponsors = new SponsorsController($app);
$admin = new SponsorsAdminController($app);

$router->get('/sponsors/{id}/click', [$sponsors, 'click']);

$router->get('/admin/sponsors', [$admin, 'index']);
$router->post('/admin/sponsors', [$admin, 'create']);
$router->post('/admin/sponsors/{id}/toggle', [$admin, 'toggle']);
$router->post('/admin/sponsors/{id}/delete', [$admin, 'delete']);

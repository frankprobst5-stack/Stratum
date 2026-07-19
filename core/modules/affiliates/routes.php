<?php

declare(strict_types=1);

use Stratum\Modules\Affiliates\AffiliatesAdminController;
use Stratum\Modules\Affiliates\AffiliatesController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$affiliates = new AffiliatesController($app);
$admin = new AffiliatesAdminController($app);

$router->get('/affiliates', [$affiliates, 'index']);
$router->get('/affiliates/{id}/visit', [$affiliates, 'visit']);

$router->get('/admin/affiliates', [$admin, 'index']);
$router->post('/admin/affiliates', [$admin, 'create']);
$router->post('/admin/affiliates/{id}/toggle', [$admin, 'toggle']);
$router->post('/admin/affiliates/{id}/delete', [$admin, 'delete']);

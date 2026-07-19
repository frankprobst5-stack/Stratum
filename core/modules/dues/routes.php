<?php

declare(strict_types=1);

use Stratum\Modules\Dues\DuesAdminController;
use Stratum\Modules\Dues\DuesController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$dues = new DuesController($app);
$admin = new DuesAdminController($app);

$router->get('/dues', [$dues, 'index']);
$router->get('/dues/plans/{id}', [$dues, 'plan']);
$router->post('/dues/plans/{id}/pay', [$dues, 'recordIntent']);

$router->get('/admin/dues', [$admin, 'index']);
$router->post('/admin/dues/plans', [$admin, 'createPlan']);
$router->post('/admin/dues/plans/{id}/toggle', [$admin, 'togglePlanActive']);
$router->post('/admin/dues/payments/{id}/confirm', [$admin, 'confirmPayment']);

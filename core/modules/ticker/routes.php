<?php

declare(strict_types=1);

use Stratum\Modules\Ticker\TickerAdminController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$admin = new TickerAdminController($app);

$router->get('/admin/ticker', [$admin, 'index']);
$router->post('/admin/ticker/messages', [$admin, 'create']);
$router->post('/admin/ticker/messages/{id}/update', [$admin, 'update']);
$router->post('/admin/ticker/messages/{id}/toggle', [$admin, 'toggle']);
$router->post('/admin/ticker/messages/{id}/delete', [$admin, 'delete']);

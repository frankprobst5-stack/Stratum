<?php

declare(strict_types=1);

use Stratum\Modules\Commerce\CommerceAdminController;
use Stratum\Modules\Commerce\CommerceController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$commerce = new CommerceController($app);
$admin = new CommerceAdminController($app);

$router->get('/shop', [$commerce, 'index']);
$router->get('/shop/products/{id}', [$commerce, 'product']);
$router->post('/shop/products/{id}/purchase', [$commerce, 'recordIntent']);
$router->get('/shop/products/{id}/download', [$commerce, 'downloadFile']);

$router->get('/admin/commerce', [$admin, 'index']);
$router->post('/admin/commerce/products', [$admin, 'createProduct']);
$router->post('/admin/commerce/products/{id}/toggle', [$admin, 'toggleProductActive']);
$router->post('/admin/commerce/purchases/{id}/confirm', [$admin, 'confirmPurchase']);

<?php

declare(strict_types=1);

use Stratum\Modules\Links\LinksAdminController;
use Stratum\Modules\Links\LinksController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$links = new LinksController($app);
$admin = new LinksAdminController($app);

$router->get('/links', [$links, 'index']);
$router->get('/links/submit', [$links, 'showCreate']);
$router->post('/links/submit', [$links, 'create']);
$router->get('/links/{id}/visit', [$links, 'visit']);

$router->get('/admin/links', [$admin, 'index']);
$router->post('/admin/links/categories', [$admin, 'createCategory']);
$router->post('/admin/links/{id}/delete', [$admin, 'deleteLink']);

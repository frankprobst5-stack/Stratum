<?php

declare(strict_types=1);

use Stratum\Modules\Pages\PageController;
use Stratum\Modules\Pages\PagesAdminController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$public = new PageController($app);
$admin = new PagesAdminController($app);

// Literal paths registered before {id}-pattern routes, same ordering care as articles/routes.php.
$router->get('/admin/pages', [$admin, 'index']);
$router->get('/admin/pages/create', [$admin, 'showCreate']);
$router->post('/admin/pages/create', [$admin, 'create']);
$router->get('/admin/pages/{id}/edit', [$admin, 'showEdit']);
$router->post('/admin/pages/{id}/edit', [$admin, 'update']);
$router->post('/admin/pages/{id}/delete', [$admin, 'delete']);

$router->get('/pages/{slug}', [$public, 'show']);

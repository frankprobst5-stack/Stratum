<?php

declare(strict_types=1);

use Stratum\Modules\Forms\FormsAdminController;
use Stratum\Modules\Forms\FormsController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$forms = new FormsController($app);
$admin = new FormsAdminController($app);

$router->get('/forms', [$forms, 'index']);
$router->get('/forms/{slug}', [$forms, 'show']);
$router->post('/forms/{slug}', [$forms, 'submit']);

$router->get('/admin/forms', [$admin, 'index']);
$router->get('/admin/forms/create', [$admin, 'showCreate']);
$router->post('/admin/forms/create', [$admin, 'create']);
$router->get('/admin/forms/{id}', [$admin, 'edit']);
$router->post('/admin/forms/{id}/fields', [$admin, 'addField']);
$router->post('/admin/forms/{id}/fields/{fieldId}/delete', [$admin, 'deleteField']);
$router->post('/admin/forms/{id}/publish', [$admin, 'publish']);
$router->post('/admin/forms/{id}/close', [$admin, 'close']);
$router->post('/admin/forms/{id}/delete', [$admin, 'deleteForm']);
$router->get('/admin/forms/{id}/results', [$admin, 'results']);

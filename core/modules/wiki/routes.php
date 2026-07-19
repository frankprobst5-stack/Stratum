<?php

declare(strict_types=1);

use Stratum\Modules\Wiki\WikiAdminController;
use Stratum\Modules\Wiki\WikiController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$wiki = new WikiController($app);
$admin = new WikiAdminController($app);

$router->get('/wiki', [$wiki, 'index']);

// Literal path registered before the {slug}-pattern route below, or
// "create" would be swallowed as a :slug value — same ordering discipline
// as articles/pages/forum routes.php.
$router->get('/wiki/create', [$wiki, 'showCreate']);
$router->post('/wiki/create', [$wiki, 'create']);

$router->get('/wiki/{slug}/edit', [$wiki, 'showEdit']);
$router->post('/wiki/{slug}/edit', [$wiki, 'update']);
$router->get('/wiki/{slug}/history', [$wiki, 'history']);
$router->get('/wiki/{slug}/history/{revisionId}', [$wiki, 'showRevision']);
$router->post('/wiki/{slug}/history/{revisionId}/restore', [$wiki, 'restoreRevision']);
$router->get('/wiki/{slug}', [$wiki, 'show']);

$router->get('/admin/wiki', [$admin, 'index']);
$router->post('/admin/wiki/categories', [$admin, 'createCategory']);
$router->post('/admin/wiki/pages/{id}/delete', [$admin, 'deletePage']);

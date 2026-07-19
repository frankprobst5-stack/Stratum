<?php

declare(strict_types=1);

use Stratum\Modules\Articles\ArticleController;
use Stratum\Modules\Articles\ArticlesAdminController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$public = new ArticleController($app);
$admin = new ArticlesAdminController($app);

$router->get('/articles', [$public, 'index']);
$router->get('/articles/{slug}/history', [$public, 'history']);
$router->get('/articles/{slug}/history/{revisionId}', [$public, 'showRevision']);
$router->post('/articles/{slug}/history/{revisionId}/restore', [$public, 'restoreRevision']);
$router->get('/articles/{slug}', [$public, 'show']);

// Literal paths must be registered before the {id}-pattern routes below,
// or "create"/"categories" would be swallowed as an :id value.
$router->get('/admin/articles', [$admin, 'index']);
$router->get('/admin/articles/create', [$admin, 'showCreate']);
$router->post('/admin/articles/create', [$admin, 'create']);
$router->post('/admin/articles/categories', [$admin, 'createCategory']);
$router->get('/admin/articles/{id}/edit', [$admin, 'showEdit']);
$router->post('/admin/articles/{id}/edit', [$admin, 'update']);
$router->post('/admin/articles/{id}/delete', [$admin, 'delete']);

<?php

declare(strict_types=1);

use Stratum\Modules\Newsletter\NewsletterAdminController;
use Stratum\Modules\Newsletter\NewsletterController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$newsletter = new NewsletterController($app);
$admin = new NewsletterAdminController($app);

$router->get('/newsletter', [$newsletter, 'index']);
$router->get('/newsletter/{slug}', [$newsletter, 'issueRedirect']);
$router->get('/newsletter/{slug}/{position}', [$newsletter, 'page']);

$router->get('/admin/newsletter', [$admin, 'index']);
$router->post('/admin/newsletter/issues', [$admin, 'createIssue']);
$router->post('/admin/newsletter/issues/{id}/toggle', [$admin, 'togglePublish']);
$router->get('/admin/newsletter/{id}/pages', [$admin, 'pages']);
$router->post('/admin/newsletter/{id}/pages', [$admin, 'addPage']);
$router->post('/admin/newsletter/pages/{pageId}', [$admin, 'updatePage']);
$router->post('/admin/newsletter/pages/{pageId}/delete', [$admin, 'deletePage']);
$router->post('/admin/newsletter/pages/{pageId}/up', [$admin, 'movePageUp']);
$router->post('/admin/newsletter/pages/{pageId}/down', [$admin, 'movePageDown']);

<?php

declare(strict_types=1);

use Stratum\Modules\Moderation\ModerationAdminController;
use Stratum\Modules\Moderation\ModerationController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$moderation = new ModerationController($app);
$admin = new ModerationAdminController($app);

$router->get('/reports/new', [$moderation, 'showCreate']);
$router->post('/reports', [$moderation, 'create']);

$router->get('/admin/moderation', [$admin, 'index']);
$router->post('/admin/moderation/reports/{id}/resolve', [$admin, 'resolve']);
$router->post('/admin/moderation/reports/{id}/dismiss', [$admin, 'dismiss']);

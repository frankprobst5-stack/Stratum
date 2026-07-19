<?php

declare(strict_types=1);

use Stratum\Modules\Notifications\NotificationsController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$notifications = new NotificationsController($app);

$router->get('/notifications', [$notifications, 'index']);
$router->post('/notifications/{id}/read', [$notifications, 'markRead']);
$router->post('/notifications/read-all', [$notifications, 'markAllRead']);

<?php

declare(strict_types=1);

use Stratum\Modules\Messages\MessagesController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$messages = new MessagesController($app);

$router->get('/messages', [$messages, 'index']);
$router->post('/messages/start', [$messages, 'start']);
$router->get('/messages/{id}', [$messages, 'conversation']);
$router->post('/messages/{id}/reply', [$messages, 'reply']);

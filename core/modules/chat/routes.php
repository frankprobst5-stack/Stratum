<?php

declare(strict_types=1);

use Stratum\Modules\Chat\ChatAdminController;
use Stratum\Modules\Chat\ChatController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$chat = new ChatController($app);
$admin = new ChatAdminController($app);

$router->get('/chat', [$chat, 'index']);
$router->post('/chat/rooms/create', [$chat, 'createRoom']);
$router->get('/chat/rooms/{id}', [$chat, 'room']);
$router->post('/chat/rooms/{id}/messages', [$chat, 'postMessage']);
$router->get('/chat/rooms/{id}/messages', [$chat, 'pollMessages']);
$router->post('/chat/rooms/{id}/leave', [$chat, 'leaveRoom']);
$router->post('/chat/rooms/{id}/invite', [$chat, 'invite']);

$router->get('/admin/chat', [$admin, 'index']);
$router->post('/admin/chat/create', [$admin, 'create']);
$router->post('/admin/chat/{id}/update', [$admin, 'update']);
$router->post('/admin/chat/{id}/delete', [$admin, 'delete']);
$router->post('/admin/chat/{id}/members/add', [$admin, 'addMember']);
$router->post('/admin/chat/{id}/members/{userId}/remove', [$admin, 'removeMember']);

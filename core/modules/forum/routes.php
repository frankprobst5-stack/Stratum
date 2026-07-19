<?php

declare(strict_types=1);

use Stratum\Modules\Forum\ForumAdminController;
use Stratum\Modules\Forum\ForumController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$forum = new ForumController($app);
$admin = new ForumAdminController($app);

$router->get('/forum', [$forum, 'index']);
$router->get('/forum/boards/{slug}', [$forum, 'board']);
$router->post('/forum/boards/{slug}/topics', [$forum, 'createTopic']);
$router->get('/forum/topics/{id}', [$forum, 'topic']);
$router->post('/forum/topics/{id}/reply', [$forum, 'reply']);
$router->post('/forum/topics/{id}/pin', [$forum, 'pin']);
$router->post('/forum/topics/{id}/unpin', [$forum, 'unpin']);
$router->post('/forum/topics/{id}/lock', [$forum, 'lock']);
$router->post('/forum/topics/{id}/unlock', [$forum, 'unlock']);
$router->post('/forum/topics/{id}/delete', [$forum, 'deleteTopic']);
$router->post('/forum/topics/{id}/poll/vote', [$forum, 'votePoll']);
$router->post('/forum/posts/{id}/delete', [$forum, 'deletePost']);
$router->post('/forum/posts/{id}/like', [$forum, 'toggleLike']);
$router->get('/forum/attachments/{id}', [$forum, 'downloadAttachment']);

$router->get('/admin/forum', [$admin, 'index']);
$router->post('/admin/forum/categories', [$admin, 'createCategory']);
$router->post('/admin/forum/boards', [$admin, 'createBoard']);
$router->get('/admin/forum/boards/{id}/moderators', [$admin, 'boardModerators']);
$router->post('/admin/forum/boards/{id}/moderators', [$admin, 'addBoardModerator']);
$router->post('/admin/forum/boards/{id}/moderators/{userId}/remove', [$admin, 'removeBoardModerator']);

<?php

declare(strict_types=1);

use Stratum\Modules\Users\AuthController;
use Stratum\Modules\Users\FriendsController;
use Stratum\Modules\Users\MemberProfileController;
use Stratum\Modules\Users\ProfileController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$controller = new AuthController($app);

$router->get('/login', [$controller, 'showLogin']);
$router->post('/login', [$controller, 'login']);
$router->post('/logout', [$controller, 'logout']);

$profile = new ProfileController($app);

$router->get('/profile', [$profile, 'show']);
$router->post('/profile', [$profile, 'update']);
$router->get('/profile/export', [$profile, 'export']);
$router->post('/profile/delete', [$profile, 'delete']);

$friends = new FriendsController($app);
$router->get('/friends', [$friends, 'index']);

$memberProfile = new MemberProfileController($app);
$router->get('/members/{username}', [$memberProfile, 'show']);
$router->post('/members/{username}/friend/request', [$memberProfile, 'sendFriendRequest']);
$router->post('/members/{username}/friend/accept', [$memberProfile, 'acceptFriendRequest']);
$router->post('/members/{username}/friend/decline', [$memberProfile, 'declineFriendRequest']);
$router->post('/members/{username}/friend/remove', [$memberProfile, 'removeFriend']);
$router->post('/members/{username}/follow', [$memberProfile, 'toggleFollow']);

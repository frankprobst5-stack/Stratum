<?php

declare(strict_types=1);

use Stratum\Modules\Membership\MembershipAdminController;
use Stratum\Modules\Membership\MembershipController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$membership = new MembershipController($app);
$admin = new MembershipAdminController($app);

$router->get('/register', [$membership, 'showRegister']);
$router->post('/register', [$membership, 'register']);

$router->get('/admin/membership', [$admin, 'index']);
$router->post('/admin/membership/fields', [$admin, 'createField']);
$router->post('/admin/membership/fields/{id}/delete', [$admin, 'deleteField']);
$router->post('/admin/membership/applications/{id}/approve', [$admin, 'approve']);
$router->post('/admin/membership/applications/{id}/reject', [$admin, 'reject']);

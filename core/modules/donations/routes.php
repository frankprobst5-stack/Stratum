<?php

declare(strict_types=1);

use Stratum\Modules\Donations\DonationAdminController;
use Stratum\Modules\Donations\DonationController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$donations = new DonationController($app);
$admin = new DonationAdminController($app);

$router->get('/donations', [$donations, 'index']);
$router->get('/donations/campaigns/{id}', [$donations, 'campaign']);
$router->post('/donations/campaigns/{id}/contribute', [$donations, 'recordIntent']);

$router->get('/admin/donations', [$admin, 'index']);
$router->post('/admin/donations/campaigns', [$admin, 'createCampaign']);
$router->post('/admin/donations/campaigns/{id}/toggle', [$admin, 'toggleCampaignActive']);
$router->post('/admin/donations/contributions', [$admin, 'recordContribution']);
$router->post('/admin/donations/contributions/{id}/confirm', [$admin, 'confirmContribution']);

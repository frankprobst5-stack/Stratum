<?php

declare(strict_types=1);

use Stratum\Modules\Ads\AdsAdminController;
use Stratum\Modules\Ads\AdsController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$ads = new AdsController($app);
$admin = new AdsAdminController($app);

$router->get('/ads/banners/{id}/click', [$ads, 'click']);

$router->get('/admin/ads', [$admin, 'index']);
$router->post('/admin/ads/advertisers', [$admin, 'createAdvertiser']);
$router->post('/admin/ads/campaigns', [$admin, 'createCampaign']);
$router->post('/admin/ads/campaigns/{id}/toggle', [$admin, 'toggleCampaign']);
$router->post('/admin/ads/banners', [$admin, 'createBanner']);
$router->post('/admin/ads/banners/{id}/toggle', [$admin, 'toggleBanner']);

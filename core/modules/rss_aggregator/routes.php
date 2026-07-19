<?php

declare(strict_types=1);

use Stratum\Modules\RssAggregator\RssAdminController;
use Stratum\Modules\RssAggregator\RssController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$rss = new RssController($app);
$admin = new RssAdminController($app);

$router->get('/feeds', [$rss, 'index']);
$router->get('/feed.xml', [$rss, 'feedXml']);

$router->get('/admin/rss', [$admin, 'index']);
$router->post('/admin/rss/sources', [$admin, 'create']);
$router->post('/admin/rss/sources/{id}/toggle', [$admin, 'toggle']);
$router->post('/admin/rss/sources/{id}/delete', [$admin, 'delete']);
$router->post('/admin/rss/sources/{id}/refresh', [$admin, 'refresh']);
$router->post('/admin/rss/sources/{id}/auto-publish', [$admin, 'toggleAutoPublish']);

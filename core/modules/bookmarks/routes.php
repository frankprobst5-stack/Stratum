<?php

declare(strict_types=1);

use Stratum\Modules\Bookmarks\BookmarkController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$bookmarks = new BookmarkController($app);

$router->get('/bookmarks', [$bookmarks, 'index']);
$router->post('/bookmarks/toggle', [$bookmarks, 'toggle']);

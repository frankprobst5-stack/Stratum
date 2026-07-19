<?php

declare(strict_types=1);

use Stratum\Modules\Tags\TagController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$tags = new TagController($app);

$router->get('/tags', [$tags, 'index']);
$router->get('/tags/{slug}', [$tags, 'show']);

<?php

declare(strict_types=1);

use Stratum\Modules\Presence\PresenceController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$presence = new PresenceController($app);

$router->get('/online', [$presence, 'index']);

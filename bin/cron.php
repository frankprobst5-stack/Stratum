#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Stratum CMS — cron entrypoint. Not installed into a system crontab by
 * this script; an operator adds one line in real deployment, e.g.:
 *   0 3 * * * php /path/to/stratum/bin/cron.php >> storage/logs/cron.log 2>&1
 *
 * Builds the same App container public/index.php does (minus the
 * HTTP-specific pieces — no Request/Router dispatch), boots modules so
 * their registerHooks() run, then fires the 'cron.daily' hook once under a
 * non-blocking exclusive file lock so an overlapping invocation skips
 * instead of running concurrently.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

use Stratum\Core\ApiRateLimiter;
use Stratum\Core\App;
use Stratum\Core\Auth;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\Logger;
use Stratum\Core\ModuleManager;
use Stratum\Core\PermissionEngine;
use Stratum\Core\Router;
use Stratum\Core\Session;
use Stratum\Core\TemplateEngine;
use Stratum\Modules\Users\AuthService;

/** @var string $rootDir @var \Stratum\Core\Config $config @var \Stratum\Core\Database $db */
[$rootDir, $config, $db] = require __DIR__ . '/../core/bootstrap.php';

$logger = new Logger($db, $rootDir . '/storage/logs');

$lockPath = $rootDir . '/storage/cron.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $logger->info('cron.daily skipped — a run is already in progress.');
    exit(0);
}

try {
    $session = new Session($config->get('APP_ENV') === 'production');
    $hooks = new HookRegistry();
    $blocks = new BlockRegistry($db);
    $templates = new TemplateEngine(
        $rootDir . '/themes',
        $rootDir . '/core/modules',
        $rootDir . '/core/admin',
        'default',
        $rootDir . '/storage/themes',
        $rootDir . '/storage/addons'
    );
    $permissions = new PermissionEngine($db);

    require_once $rootDir . '/core/modules/users/services/AuthService.php';
    $auth = new Auth($session, $db, new AuthService($db), $permissions);

    $router = new Router();
    // Custom (uploaded) addons need to boot here too, same as public/index.php
    // — an addon's own cron.daily listener, if it has one, wouldn't otherwise
    // ever get registered.
    $modules = new ModuleManager($db, $rootDir . '/core/modules', $rootDir . '/storage/addons');

    $app = new App($rootDir, $config, $db, $session, $auth, $router, $hooks, $blocks, $templates, $logger, $modules, $permissions);
    $modules->boot($app);

    // Rate-limit window pruning (Stage 10) isn't tied to any one module —
    // same reasoning dues' own cron.daily listener has, just registered
    // directly here since ApiRateLimiter is core infrastructure, not a
    // module with its own Module.php to register from.
    $hooks->listen('cron.daily', static function () use ($db): void {
        (new ApiRateLimiter($db))->pruneOldWindows();
    });

    $logger->info('cron.daily starting.');
    $errors = $hooks->fire('cron.daily');

    foreach ($errors as $error) {
        $logger->error('cron.daily listener failed: ' . $error->getMessage(), ['exception' => get_class($error)]);
    }

    $logger->info('cron.daily finished.', ['listener_errors' => count($errors)]);
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

<?php

declare(strict_types=1);

use Stratum\Core\Config;
use Stratum\Core\Database;

/**
 * Shared bootstrap for both the web front controller and the CLI installer:
 * just the two pieces every entry point needs (Config, Database). Everything
 * else (Session, Router, Auth, ModuleManager...) is request/CLI-context
 * specific and built by the caller.
 */
$rootDir = dirname(__DIR__);
$config = new Config($rootDir . '/.env');

// PHP defaults to UTC when nothing sets this, but MySQL's NOW() and this
// server's OS clock use whatever local timezone the host is configured
// with — on a real (unknown) shared host, that's rarely UTC. Left unset,
// every DateTimeImmutable parse of a browser's `datetime-local` input
// (calendar events, scheduled article publishing) silently disagrees with
// both the admin's own clock and MySQL's NOW(), by whatever the offset
// happens to be — a live, real 5-hour mismatch found on this dev machine
// while testing article scheduling, not a hypothetical. Configurable via
// .env since it's genuinely per-install (each club's server, and each
// club's own local time, could differ), defaulting to UTC — a safe,
// unsurprising choice for a fresh install that never has to be *wrong*,
// only imprecise until the admin sets it to match their own locale.
date_default_timezone_set($config->get('APP_TIMEZONE', 'UTC'));

$db = new Database($config);

return [$rootDir, $config, $db];

<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap (see phpunit.xml). Deliberately not a reuse of
 * core/bootstrap.php, which hardcodes .env (the real dev/prod database) —
 * this points at .env.testing / the throwaway Docker container instead,
 * see docs/roadmap.md's Stage 10 test-suite entry for why.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Tests\Support\TestEnvironment;

TestEnvironment::boot(dirname(__DIR__));

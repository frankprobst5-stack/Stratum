#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Stratum CMS — CLI installer. Connects to the DB, runs pending migrations,
 * and creates the first admin account if none exists yet. Safe to re-run.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Stratum\Core\MigrationRunner;
use Stratum\Core\PermissionEngine;
use Stratum\Modules\Users\AuthService;

/** @var string $rootDir @var \Stratum\Core\Config $config @var \Stratum\Core\Database $db */
[$rootDir, $config, $db] = require __DIR__ . '/../core/bootstrap.php';

function out(string $line): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);

    return $value === false ? '' : trim($value);
}

function promptHidden(string $label): string
{
    fwrite(STDOUT, $label);

    if (stripos(PHP_OS, 'WIN') === 0) {
        $value = fgets(STDIN);

        return $value === false ? '' : trim($value);
    }

    system('stty -echo 2>/dev/null');
    $value = fgets(STDIN);
    system('stty echo 2>/dev/null');
    fwrite(STDOUT, PHP_EOL);

    return $value === false ? '' : trim($value);
}

try {
    $db->pdo(); // forces the PDO connection to prove credentials work
    out("Connected to database '{$config->get('DB_DATABASE')}' as '{$config->get('DB_USERNAME')}'.");
} catch (\Throwable $e) {
    out('Could not connect to the database: ' . $e->getMessage());
    exit(1);
}

$runner = new MigrationRunner($db);
$results = $runner->runAll($rootDir);

foreach ($results as $id => $applied) {
    $label = $id === 'core' ? 'core' : $id;
    out(count($applied) > 0
        ? "Applied {$label} migrations: " . implode(', ', $applied)
        : "{$label} migrations already up to date.");
}

require_once $rootDir . '/core/modules/users/services/AuthService.php';
$authService = new AuthService($db);
$permissions = new PermissionEngine($db);

$adminRole = $db->fetchOne("SELECT id FROM " . $db->table('roles') . " WHERE name = 'admin'");
if ($adminRole === null) {
    out('Expected the admin role to exist after migrations — something went wrong.');
    exit(1);
}

$existingAdmin = $db->fetchOne(
    'SELECT u.id FROM ' . $db->table('users') . ' u
     JOIN ' . $db->table('users_roles') . ' ur ON ur.user_id = u.id
     WHERE ur.role_id = :role_id LIMIT 1',
    ['role_id' => $adminRole['id']]
);

if ($existingAdmin !== null) {
    out('An admin account already exists — skipping account creation.');
    out('Install complete.');
    exit(0);
}

out('');
out('Create the first admin account:');

while (true) {
    $username = prompt('Username: ');
    $email = prompt('Email: ');
    $password = promptHidden('Password: ');
    $confirm = promptHidden('Confirm password: ');

    if ($username === '' || $email === '' || $password === '') {
        out('Username, email, and password are all required. Try again.');
        continue;
    }

    if ($password !== $confirm) {
        out('Passwords did not match. Try again.');
        continue;
    }

    if (strlen($password) < 12) {
        out('Password must be at least 12 characters. Try again.');
        continue;
    }

    if ($authService->usernameOrEmailExists($username, $email)) {
        out('That username or email is already taken. Try again.');
        continue;
    }

    $userId = $authService->createUser($username, $email, $password);
    $permissions->setRolesForUser((int) $userId, [(int) $adminRole['id']]);
    out("Admin account '{$username}' created.");
    break;
}

out('Install complete.');

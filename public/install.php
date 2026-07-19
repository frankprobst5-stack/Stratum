<?php

declare(strict_types=1);

/**
 * Stratum CMS — web installer.
 *
 * Deliberately standalone: does NOT go through core/bootstrap.php, because
 * Config::__construct() throws immediately if .env doesn't exist yet, and
 * Database's constructor connects to MySQL immediately in its constructor
 * — both fatal before this script could ever render a first "let's set up
 * your database" screen. Same reason bin/install.php (the CLI installer)
 * stays a separate entry point too. This script only builds a Config/
 * Database once .env actually exists — either already present, or just
 * written by the DB step below.
 *
 * All five steps (requirements, DB connect, migrations, admin account,
 * done) live in this one file rather than being split across several —
 * matches bin/install.php's own single-script shape, and every step needs
 * the same currentStep() state-detection anyway. State is re-derived from
 * disk/DB on every request, never trusted from a hidden form field, so the
 * wizard is safely resumable: reloading, closing the tab mid-setup, or a
 * host timeout never corrupts anything, it just picks up where it left off.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Stratum\Core\Config;
use Stratum\Core\Database;
use Stratum\Core\MigrationRunner;
use Stratum\Core\PermissionEngine;
use Stratum\Modules\Users\AuthService;

require_once dirname(__DIR__) . '/core/modules/users/services/AuthService.php';

$rootDir = dirname(__DIR__);
$envFile = $rootDir . '/.env';
$lockFile = $rootDir . '/storage/install.lock';

const REQUIRED_PHP_VERSION = '8.2.0';
const REQUIRED_EXTENSIONS = ['pdo_mysql', 'gd', 'exif', 'fileinfo', 'mbstring'];

session_start();

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (!isset($_SESSION['install_csrf']) || !is_string($_SESSION['install_csrf'])) {
        $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['install_csrf'];
}

function verifyCsrf(): bool
{
    $token = $_POST['_csrf'] ?? null;
    $expected = $_SESSION['install_csrf'] ?? null;

    return is_string($token) && is_string($expected) && hash_equals($expected, $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function redirectToSelf(): never
{
    header('Location: install.php');
    exit;
}

/** Strips characters that would corrupt a single-line KEY=value .env entry. */
function sanitizeEnvValue(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

/** @return array<int, array{label: string, ok: bool}> */
function checkRequirements(string $rootDir): array
{
    $checks = [
        [
            'label' => 'PHP version ' . PHP_VERSION . ' (>= ' . REQUIRED_PHP_VERSION . ' required)',
            'ok' => version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '>='),
        ],
    ];

    foreach (REQUIRED_EXTENSIONS as $ext) {
        $checks[] = ['label' => "PHP extension: {$ext}", 'ok' => extension_loaded($ext)];
    }

    $checks[] = [
        'label' => 'Project root is writable (needed to create .env)',
        'ok' => is_writable($rootDir),
    ];

    foreach (['storage', 'storage/uploads', 'storage/cache', 'storage/logs'] as $dir) {
        $checks[] = [
            'label' => "{$dir}/ is writable",
            'ok' => is_dir($rootDir . '/' . $dir) && is_writable($rootDir . '/' . $dir),
        ];
    }

    return $checks;
}

/** @param array<string, string> $values */
function writeEnvFile(string $envFile, array $values): void
{
    $lines = [
        'APP_ENV=production',
        'APP_DEBUG=false',
        'APP_TIMEZONE=' . $values['timezone'],
        '',
        'DB_HOST=' . $values['host'],
        'DB_PORT=' . $values['port'],
        'DB_DATABASE=' . $values['database'],
        'DB_USERNAME=' . $values['username'],
        'DB_PASSWORD=' . $values['password'],
        'DB_PREFIX=' . $values['prefix'],
        'DB_CHARSET=utf8mb4',
        '',
    ];

    if (file_put_contents($envFile, implode("\n", $lines)) === false) {
        throw new \RuntimeException('Could not write .env — check the project root is writable.');
    }
}

/**
 * @param array<string, string> $values
 * @return array{ok: bool, error: ?string}
 */
function testDbConnection(array $values): array
{
    try {
        $dsn = "mysql:host={$values['host']};port={$values['port']};dbname={$values['database']};charset=utf8mb4";
        new \PDO($dsn, $values['username'], $values['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        return ['ok' => true, 'error' => null];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Re-derives what the wizard should show right now, purely from disk/DB
 * state. Mirrors the same layered checks bin/install.php already does
 * (try/catch around the roles table, then look for an existing admin).
 */
function currentStep(string $envFile): string
{
    if (!is_file($envFile)) {
        return 'requirements';
    }

    try {
        $config = new Config($envFile);
        $db = new Database($config);
        $db->pdo(); // forces the connection attempt
    } catch (\Throwable) {
        return 'db';
    }

    try {
        $adminRole = $db->fetchOne('SELECT id FROM ' . $db->table('roles') . " WHERE name = 'admin'");
    } catch (\Throwable) {
        // Permission tables don't exist yet — migrations haven't run (or didn't finish).
        return 'migrate';
    }

    if ($adminRole === null) {
        return 'migrate';
    }

    $existingAdmin = $db->fetchOne(
        'SELECT u.id FROM ' . $db->table('users') . ' u
         JOIN ' . $db->table('users_roles') . ' ur ON ur.user_id = u.id
         WHERE ur.role_id = :role_id LIMIT 1',
        ['role_id' => $adminRole['id']]
    );

    return $existingAdmin === null ? 'admin' : 'done';
}

function renderPage(string $title, string $body): never
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' — Stratum CMS Setup</title>'
        . '<style>
            body { font-family: system-ui, sans-serif; margin: 0; background: #f4f5f7; color: #1a1a1a; }
            header { background: #12141c; color: #fff; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 0.6rem; }
            header .brand { font-weight: 700; font-size: 1.1rem; }
            header img { height: 28px; width: auto; }
            .wrap { max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
            .card { background: #fff; border-radius: 8px; padding: 1.5rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            h1 { font-size: 1.4rem; margin-top: 0; }
            label { display: block; font-weight: 600; margin: 0.9rem 0 0.25rem; }
            input[type=text], input[type=password], input[type=email] { width: 100%; padding: 0.5rem; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
            button { margin-top: 1.25rem; padding: 0.6rem 1.4rem; background: #2f6fed; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
            button:hover { background: #2558c4; }
            ul.checks { list-style: none; padding: 0; }
            ul.checks li { padding: 0.35rem 0; }
            .ok { color: #0a7d2c; } .fail { color: #c0392b; }
            .error-box { background: #fdecea; border: 1px solid #f5c6cb; color: #611a15; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; white-space: pre-wrap; }
            .hint { color: #666; font-size: 0.85rem; }
        </style></head><body>'
        . '<header><img src="/assets/images/icon-circle.png" alt=""><span class="brand">Stratum CMS — Setup</span></header>'
        . '<div class="wrap"><div class="card">' . $body . '</div></div>'
        . '</body></html>';
    exit;
}

// A completed install locks this script permanently — re-hitting /install.php
// on a live site must never be able to touch .env or create a second admin.
if (is_file($lockFile)) {
    renderPage('Already installed', '<h1>Stratum is already installed</h1>'
        . '<p>Setup has already been completed on this site. If you need to run it again '
        . '(e.g. to fix a broken install), delete <code>storage/install.lock</code> via FTP/file manager first.</p>'
        . '<p><a href="/">Go to the homepage</a></p>');
}

$step = currentStep($envFile);

// currentStep() only knows about .env/DB state, so it can never itself
// return 'requirements' -> 'db'. Once checks pass, "Continue" advances
// past this step within the same request rather than needing its own
// persisted state — there's nothing to persist, the checks are re-run
// fresh on every load regardless. Two independent triggers, not just the
// query string: the DB form's own POST body (its 'database' field) is
// unambiguous evidence of the DB step, and doesn't depend on a browser
// correctly carrying ?proceed=1 through an action-less form submission.
$requestsDbStep = ($_GET['proceed'] ?? '') === '1'
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('database', $_POST));

if ($step === 'requirements' && $requestsDbStep && !in_array(false, array_column(checkRequirements($rootDir), 'ok'), true)) {
    $step = 'db';
}

if ($step === 'requirements') {
    $checks = checkRequirements($rootDir);
    $allOk = !in_array(false, array_column($checks, 'ok'), true);

    $items = '';
    foreach ($checks as $check) {
        $icon = $check['ok'] ? '<span class="ok">&#10003;</span>' : '<span class="fail">&#10007;</span>';
        $items .= '<li>' . $icon . ' ' . e($check['label']) . '</li>';
    }

    $body = '<h1>Step 1 of 4 — Requirements</h1><ul class="checks">' . $items . '</ul>';
    $body .= $allOk
        ? '<p class="hint">All checks passed.</p><form method="get"><input type="hidden" name="proceed" value="1"><button type="submit">Continue</button></form>'
        : '<p class="hint">Fix the items above, then reload this page. Nothing has been changed yet.</p>';

    renderPage('Requirements', $body);
}

if ($step === 'db') {
    $error = null;
    $values = [
        'host' => $_POST['host'] ?? '127.0.0.1',
        'port' => $_POST['port'] ?? '3306',
        'database' => $_POST['database'] ?? '',
        'username' => $_POST['username'] ?? '',
        'password' => $_POST['password'] ?? '',
        'prefix' => $_POST['prefix'] ?? 'strat_',
        'timezone' => $_POST['timezone'] ?? 'UTC',
    ];

    // .env may already exist with bad/stale values (a previous failed attempt) —
    // prefill the form from it instead of always starting blank.
    if (is_file($envFile) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $existing = new Config($envFile);
        $values = [
            'host' => $existing->get('DB_HOST', '127.0.0.1'),
            'port' => $existing->get('DB_PORT', '3306'),
            'database' => $existing->get('DB_DATABASE', ''),
            'username' => $existing->get('DB_USERNAME', ''),
            'password' => $existing->get('DB_PASSWORD', ''),
            'prefix' => $existing->get('DB_PREFIX', 'strat_'),
            'timezone' => $existing->get('APP_TIMEZONE', 'UTC'),
        ];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf()) {
            $error = 'Invalid or expired form submission — please try again.';
        } else {
            $values = array_map('sanitizeEnvValue', $values);
            if ($values['database'] === '' || $values['username'] === '') {
                $error = 'Database name and username are required.';
            } elseif (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
                $error = 'Please choose a valid timezone from the list.';
            } else {
                $result = testDbConnection($values);
                if (!$result['ok']) {
                    $error = "Could not connect to the database:\n" . $result['error'];
                } else {
                    writeEnvFile($envFile, $values);
                    redirectToSelf();
                }
            }
        }
    }

    $body = '<h1>Step 2 of 4 — Database</h1>';
    if ($error !== null) {
        $body .= '<div class="error-box">' . e($error) . '</div>';
    }
    $body .= '<form method="post">' . csrfField()
        . '<label for="host">Host</label><input type="text" id="host" name="host" value="' . e($values['host']) . '" required>'
        . '<label for="port">Port</label><input type="text" id="port" name="port" value="' . e($values['port']) . '" required>'
        . '<label for="database">Database name</label><input type="text" id="database" name="database" value="' . e($values['database']) . '" required>'
        . '<label for="username">Username</label><input type="text" id="username" name="username" value="' . e($values['username']) . '" required>'
        . '<label for="password">Password</label><input type="password" id="password" name="password" value="' . e($values['password']) . '">'
        . '<label for="prefix">Table prefix</label><input type="text" id="prefix" name="prefix" value="' . e($values['prefix']) . '" required>'
        . '<label for="timezone">Timezone</label><input type="text" id="timezone" name="timezone" list="tz-list" value="' . e($values['timezone']) . '" required>'
        . '<datalist id="tz-list">' . implode('', array_map(
            static fn (string $tz): string => '<option value="' . e($tz) . '">',
            timezone_identifiers_list()
        )) . '</datalist>'
        . '<p class="hint">The database itself must already exist — most hosts create this for you in cPanel/Plesk before you get credentials. '
        . 'Set the timezone to where your club/organization is actually located — this controls scheduled publishing, event times, and timestamps sitewide.</p>'
        . '<button type="submit">Test connection &amp; continue</button></form>';

    renderPage('Database', $body);
}

if ($step === 'migrate') {
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
        try {
            $config = new Config($envFile);
            $db = new Database($config);
            (new MigrationRunner($db))->runAll($rootDir);
            redirectToSelf();
        } catch (\Throwable $e) {
            $error = 'Migration failed: ' . $e->getMessage();
        }
    }

    $body = '<h1>Step 3 of 4 — Set up the database</h1>'
        . '<p>This creates all the tables Stratum needs. Safe to run more than once if it fails partway — '
        . 'already-applied changes are tracked and skipped on retry.</p>';
    if ($error !== null) {
        $body .= '<div class="error-box">' . e($error) . '</div>';
    }
    $body .= '<form method="post">' . csrfField() . '<button type="submit">Run setup</button></form>';

    renderPage('Set up database', $body);
}

if ($step === 'admin') {
    $error = null;
    $username = '';
    $email = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf()) {
            $error = 'Invalid or expired form submission — please try again.';
        } else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['confirm'] ?? '');

            $config = new Config($envFile);
            $db = new Database($config);
            $authService = new AuthService($db);

            if ($username === '' || $email === '' || $password === '') {
                $error = 'Username, email, and password are all required.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords did not match.';
            } elseif (strlen($password) < 12) {
                $error = 'Password must be at least 12 characters.';
            } elseif ($authService->usernameOrEmailExists($username, $email)) {
                $error = 'That username or email is already taken.';
            } else {
                $adminRole = $db->fetchOne("SELECT id FROM " . $db->table('roles') . " WHERE name = 'admin'");
                $userId = $authService->createUser($username, $email, $password);
                (new PermissionEngine($db))->setRolesForUser((int) $userId, [(int) $adminRole['id']]);
                redirectToSelf();
            }
        }
    }

    $body = '<h1>Step 4 of 4 — Create your admin account</h1>';
    if ($error !== null) {
        $body .= '<div class="error-box">' . e($error) . '</div>';
    }
    $body .= '<form method="post">' . csrfField()
        . '<label for="username">Username</label><input type="text" id="username" name="username" value="' . e($username) . '" required>'
        . '<label for="email">Email</label><input type="email" id="email" name="email" value="' . e($email) . '" required>'
        . '<label for="password">Password</label><input type="password" id="password" name="password" required>'
        . '<label for="confirm">Confirm password</label><input type="password" id="confirm" name="confirm" required>'
        . '<p class="hint">At least 12 characters.</p>'
        . '<button type="submit">Create account &amp; finish</button></form>';

    renderPage('Admin account', $body);
}

// $step === 'done' — write the lock file so this script refuses to run again, then confirm.
if (!is_dir(dirname($lockFile))) {
    mkdir(dirname($lockFile), 0755, true);
}
file_put_contents($lockFile, 'Installed ' . date('Y-m-d H:i:s') . "\n");

renderPage('Install complete', '<h1>Stratum is ready</h1>'
    . '<p>Setup is complete. This installer is now locked and will refuse to run again.</p>'
    . '<p><a href="/">Go to your new site</a> &middot; <a href="/login">Log in</a></p>');

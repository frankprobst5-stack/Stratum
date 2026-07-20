<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Stratum\Core\ApiTokenService;
use Stratum\Core\App;
use Stratum\Core\Auth;
use Stratum\Core\BlockRegistry;
use Stratum\Core\Config;
use Stratum\Core\Database;
use Stratum\Core\HookRegistry;
use Stratum\Core\Logger;
use Stratum\Core\ModuleManager;
use Stratum\Core\PermissionEngine;
use Stratum\Core\Request;
use Stratum\Core\Router;
use Stratum\Core\Session;
use Stratum\Core\TemplateEngine;
use Stratum\Modules\Users\AuthService;
use Tests\Support\TestEnvironment;

/**
 * Base for every test — builds a real App around the throwaway test
 * database (see TestEnvironment), wired the same way public/index.php
 * wires one, minus the HTTP-response-only pieces (page cache, maintenance
 * mode, front-page routes) no test needs. Real business logic against a
 * real MySQL, not mocks — see docs/roadmap.md's Stage 10 test-suite entry
 * for why (this app's migrations are too MySQL-specific for a fake DB).
 */
abstract class TestCase extends BaseTestCase
{
    protected string $rootDir;
    protected Config $config;
    protected Database $db;
    protected App $app;

    /** @var int[] user ids created by createUser(), cleaned up in tearDown() */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootDir = TestEnvironment::rootDir();
        $this->config = TestEnvironment::config();
        $this->db = TestEnvironment::db();
        $this->app = $this->buildApp();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $userId) {
            $this->db->execute('DELETE FROM ' . $this->db->table('users') . ' WHERE id = :id', ['id' => $userId]);
        }
        $this->createdUserIds = [];

        // asUser()/Auth::attempt() write user_id into the real $_SESSION via
        // Session::set() — session_start() only actually runs once per test
        // *process* (Session's own constructor short-circuits if a session is
        // already active), so without this the next test's fresh Auth would
        // still resolve the previous test's logged-in user.
        $_SESSION = [];

        parent::tearDown();
    }

    /**
     * A real user row via AuthService (not a mock) plus the 'member' role —
     * replicating the exact pattern MembershipApplicationService::approve()
     * uses for real member signups (core/modules/membership/services/
     * MembershipApplicationService.php), so test users start with the same
     * baseline capabilities (forum.reply, etc.) a real approved member has,
     * not zero.
     *
     * @param array{username?: string, email?: string, password?: string} $overrides
     * @return array<string, mixed>
     */
    protected function createUser(array $overrides = []): array
    {
        $suffix = bin2hex(random_bytes(4));
        $username = $overrides['username'] ?? "test_user_{$suffix}";
        $email = $overrides['email'] ?? "test_{$suffix}@example.test";
        $password = $overrides['password'] ?? 'Test-Password-123!';

        require_once $this->rootDir . '/core/modules/users/services/AuthService.php';
        $authService = new AuthService($this->db);
        $userId = (int) $authService->createUser($username, $email, $password);
        $this->createdUserIds[] = $userId;

        $memberRole = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('roles') . " WHERE name = 'member'"
        );
        if ($memberRole !== null) {
            $this->app->permissions->setRolesForUser($userId, [(int) $memberRole['id']]);
        }

        /** @var array<string, mixed> $user */
        $user = $authService->findById($userId);

        return $user;
    }

    /** Grants $userId a capability directly, bypassing role membership — for tests that need one specific permission without pulling in everything 'member' grants. */
    protected function grantCapability(int $userId, string $capabilityKey): void
    {
        $capability = $this->app->permissions->findCapabilityByKey($capabilityKey);
        if ($capability === null) {
            throw new \RuntimeException("Unknown capability: {$capabilityKey}");
        }

        $role = $this->app->permissions->createRole('test-role-' . bin2hex(random_bytes(4)));
        $this->app->permissions->grant((int) $role, (int) $capability['id']);
        $this->app->permissions->addRoleToUser($userId, (int) $role);
    }

    /**
     * An App whose Auth already resolves $user, the same end state a real
     * login (Auth::attempt()) leaves behind — without needing real
     * credentials or an HTTP round trip. Builds a fresh App (fresh Auth,
     * fresh $resolved cache) rather than mutating $this->app, so a test
     * that checks both a logged-in and a guest view of the same endpoint
     * doesn't have to worry about Auth::user()'s internal caching.
     *
     * @param array<string, mixed> $user
     */
    protected function asUser(array $user): App
    {
        $app = $this->buildApp();
        $app->session->set('user_id', (int) $user['id']);

        return $app;
    }

    /**
     * A Request built from explicit values instead of real superglobals —
     * Request::fromGlobals() is the only public factory, so this
     * temporarily stages $_GET/$_POST/$_SERVER, builds from them, then
     * restores whatever was there before.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $server
     */
    protected function makeRequest(string $method, string $path, array $query = [], array $body = [], array $server = []): Request
    {
        $previousGet = $_GET;
        $previousPost = $_POST;
        $previousServer = $_SERVER;

        $_GET = $query;
        $_POST = $body;
        $_SERVER = array_merge($_SERVER, $server, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
        ]);

        $request = Request::fromGlobals();

        $_GET = $previousGet;
        $_POST = $previousPost;
        $_SERVER = $previousServer;

        return $request;
    }

    private function buildApp(): App
    {
        $session = new Session(false);
        $hooks = new HookRegistry();
        $blocks = new BlockRegistry($this->db);
        $templates = new TemplateEngine(
            $this->rootDir . '/themes',
            $this->rootDir . '/core/modules',
            $this->rootDir . '/core/admin',
            'default',
            $this->rootDir . '/storage/themes',
            $this->rootDir . '/storage/addons'
        );
        $permissions = new PermissionEngine($this->db);
        $request = Request::fromGlobals();

        require_once $this->rootDir . '/core/modules/users/services/AuthService.php';
        $auth = new Auth($session, $this->db, new AuthService($this->db), $permissions, $request, new ApiTokenService($this->db));

        $logger = new Logger($this->db, $this->rootDir . '/storage/logs');
        $router = new Router();
        $modules = new ModuleManager($this->db, $this->rootDir . '/core/modules', $this->rootDir . '/storage/addons');

        $app = new App(
            $this->rootDir,
            $this->config,
            $this->db,
            $session,
            $auth,
            $router,
            $hooks,
            $blocks,
            $templates,
            $logger,
            $modules,
            $permissions
        );

        $modules->boot($app);

        return $app;
    }
}

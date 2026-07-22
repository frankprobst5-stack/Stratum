<?php

declare(strict_types=1);

namespace Tests\Web;

use Stratum\Admin\DashboardController;
use Stratum\Admin\SettingsController;
use Stratum\Admin\UsersController;
use Stratum\Core\Response;
use Stratum\Modules\Articles\ArticlesAdminController;
use Stratum\Modules\Forum\ForumAdminController;
use Tests\TestCase;

/** Same purpose as PublicPagesSmokeTest — a small, representative slice
 * of /admin/* pages (dashboard, users, settings, plus two module admin
 * screens) rather than all ~30, since every admin controller shares the
 * same AdminController base and admin-layout render path — a handful is
 * enough to catch a broken admin-chrome rewrite without duplicating the
 * public suite's breadth for every module's admin screen too. */
final class AdminPagesSmokeTest extends TestCase
{
    /** @return array<int, array{0: class-string, 1: string, 2: string}> */
    private function adminRoutes(): array
    {
        return [
            [DashboardController::class, 'index', '/admin'],
            [UsersController::class, 'index', '/admin/users'],
            [SettingsController::class, 'index', '/admin/settings'],
            [ArticlesAdminController::class, 'index', '/admin/articles'],
            [ForumAdminController::class, 'index', '/admin/forum'],
        ];
    }

    public function testEveryAdminPageRendersACompletePage(): void
    {
        $admin = $this->createUser();
        $adminRole = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('roles') . " WHERE name = 'admin'"
        );
        $this->app->permissions->setRolesForUser((int) $admin['id'], [(int) $adminRole['id']]);
        $app = $this->asUser($admin);
        $failures = [];

        foreach ($this->adminRoutes() as [$controllerClass, $action, $path]) {
            $controller = new $controllerClass($app);
            $request = $this->makeRequest('GET', $path);

            try {
                /** @var Response $response */
                $response = $controller->$action($request);
            } catch (\Throwable $e) {
                $failures[] = "{$path} ({$controllerClass}::{$action}) threw " . $e::class . ': ' . $e->getMessage();
                continue;
            }

            if ($response->status() !== 200) {
                $failures[] = "{$path} ({$controllerClass}::{$action}): expected 200, got {$response->status()}";
                continue;
            }

            if (!str_contains($response->body(), '</html>')) {
                $failures[] = "{$path} ({$controllerClass}::{$action}): response body has no closing </html> — page likely broke mid-render";
            }
        }

        $this->assertSame([], $failures, "Smoke test failures:\n" . implode("\n", $failures));
    }
}

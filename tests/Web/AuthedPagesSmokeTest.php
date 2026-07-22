<?php

declare(strict_types=1);

namespace Tests\Web;

use Stratum\Core\Response;
use Stratum\Modules\Bookmarks\BookmarkController;
use Stratum\Modules\Messages\MessagesController;
use Stratum\Modules\Notifications\NotificationsController;
use Stratum\Modules\Users\FriendsController;
use Stratum\Modules\Users\ProfileController;
use Tests\TestCase;

/** Same purpose as PublicPagesSmokeTest — a broad render-completeness net
 * ahead of the CSS/template rewrite — for the handful of pages that
 * require a real logged-in member rather than a guest. */
final class AuthedPagesSmokeTest extends TestCase
{
    /** @return array<int, array{0: class-string, 1: string, 2: string}> */
    private function memberRoutes(): array
    {
        return [
            [FriendsController::class, 'index', '/friends'],
            [BookmarkController::class, 'index', '/bookmarks'],
            [MessagesController::class, 'index', '/messages'],
            [NotificationsController::class, 'index', '/notifications'],
            [ProfileController::class, 'show', '/profile'],
        ];
    }

    public function testEveryMemberPageRendersACompletePage(): void
    {
        $user = $this->createUser();
        $app = $this->asUser($user);
        $failures = [];

        foreach ($this->memberRoutes() as [$controllerClass, $action, $path]) {
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

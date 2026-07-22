<?php

declare(strict_types=1);

namespace Tests\Web;

use Stratum\Core\Response;
use Stratum\Modules\Activity\ActivityController;
use Stratum\Modules\Articles\ArticleController;
use Stratum\Modules\Calendar\CalendarController;
use Stratum\Modules\Chat\ChatController;
use Stratum\Modules\Classifieds\ClassifiedsController;
use Stratum\Modules\Commerce\CommerceController;
use Stratum\Modules\Donations\DonationController;
use Stratum\Modules\Downloads\DownloadsController;
use Stratum\Modules\Dues\DuesController;
use Stratum\Modules\Forms\FormsController;
use Stratum\Modules\Forum\ForumController;
use Stratum\Modules\Gallery\GalleryController;
use Stratum\Modules\Links\LinksController;
use Stratum\Modules\Membership\MembershipController;
use Stratum\Modules\OrgSpaces\OrgSpacesController;
use Stratum\Modules\Presence\PresenceController;
use Stratum\Modules\RssAggregator\RssController;
use Stratum\Modules\Search\SearchController;
use Stratum\Modules\Tags\TagController;
use Stratum\Modules\Users\AuthController;
use Stratum\Modules\Video\VideoController;
use Stratum\Modules\Wiki\WikiController;
use Tests\TestCase;

/**
 * Not behavioral testing (the API suite already covers real business
 * logic per module) — a broad, shallow net against every guest-visible
 * landing page, built ahead of the CSS/template rewrite so a broken
 * `include`, a renamed template variable, or a fatal mid-render error
 * shows up as one failing assertion instead of a manual re-click through
 * ~30 modules after every change. Each page just needs to render to a
 * complete, well-formed document — content correctness stays the API
 * suite's job.
 *
 * The one guest route deliberately NOT here: `/` (the homepage) is
 * registered as an inline closure in public/index.php, not a controller
 * class, so it can't be instantiated directly the way every other route
 * here is. Covered by manual live verification instead until/unless it's
 * ever extracted into a real controller.
 */
final class PublicPagesSmokeTest extends TestCase
{
    /**
     * @return array<int, array{0: class-string, 1: string, 2: string}>
     *   [controllerClass, action, path] — every one of these constructs
     *   directly with no fixture data required, since an empty list/page
     *   is exactly as valid a render target as a populated one for this
     *   suite's purpose.
     */
    private function guestRoutes(): array
    {
        return [
            [ForumController::class, 'index', '/forum'],
            [ArticleController::class, 'index', '/articles'],
            [WikiController::class, 'index', '/wiki'],
            [CalendarController::class, 'index', '/calendar'],
            [DownloadsController::class, 'index', '/downloads'],
            [GalleryController::class, 'index', '/gallery'],
            [TagController::class, 'index', '/tags'],
            [ChatController::class, 'index', '/chat'],
            [ClassifiedsController::class, 'index', '/classifieds'],
            [VideoController::class, 'index', '/videos'],
            [LinksController::class, 'index', '/links'],
            [FormsController::class, 'index', '/forms'],
            [ActivityController::class, 'index', '/activity'],
            [PresenceController::class, 'index', '/online'],
            [SearchController::class, 'index', '/search'],
            [DonationController::class, 'index', '/donations'],
            [DuesController::class, 'index', '/dues'],
            [CommerceController::class, 'index', '/shop'],
            [RssController::class, 'index', '/feeds'],
            [OrgSpacesController::class, 'index', '/organizations'],
            [AuthController::class, 'showLogin', '/login'],
            [MembershipController::class, 'showRegister', '/register'],
        ];
    }

    public function testEveryGuestPageRendersACompletePage(): void
    {
        $failures = [];

        foreach ($this->guestRoutes() as [$controllerClass, $action, $path]) {
            $controller = new $controllerClass($this->app);
            $request = $this->makeRequest('GET', $path);

            try {
                /** @var Response $response */
                $response = $controller->$action($request);
            } catch (\Throwable $e) {
                $failures[] = "{$path} ({$controllerClass}::{$action}) threw " . $e::class . ': ' . $e->getMessage();
                continue;
            }

            $this->assertPageRendered($response, "{$path} ({$controllerClass}::{$action})", $failures);
        }

        $this->assertSame([], $failures, "Smoke test failures:\n" . implode("\n", $failures));
    }

    /** @param array<int, string> $failures */
    private function assertPageRendered(Response $response, string $label, array &$failures): void
    {
        if ($response->status() !== 200) {
            $failures[] = "{$label}: expected 200, got {$response->status()}";

            return;
        }

        if (!str_contains($response->body(), '</html>')) {
            $failures[] = "{$label}: response body has no closing </html> — page likely broke mid-render";
        }
    }
}

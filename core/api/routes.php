<?php

declare(strict_types=1);

use Stratum\Api\ArticlesApiController;
use Stratum\Api\CalendarApiController;
use Stratum\Api\DownloadsApiController;
use Stratum\Api\ForumApiController;
use Stratum\Api\GalleryApiController;
use Stratum\Api\WikiApiController;

/**
 * Stage 10, first slice — the REST API foundation, proven against a real
 * first set of resources rather than attempted exhaustively in one pass.
 * See docs/roadmap.md. Registered from public/index.php alongside the
 * other core-infrastructure routes (/sitemap.xml, /robots.txt,
 * /manifest.json) — not module-toggleable, spans every module's own
 * service layer.
 *
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$articles = new ArticlesApiController($app);
$router->get('/api/v1/articles', [$articles, 'index']);
$router->get('/api/v1/articles/{slug}', [$articles, 'show']);

$forum = new ForumApiController($app);
$router->get('/api/v1/forum/boards', [$forum, 'boards']);
$router->get('/api/v1/forum/boards/{slug}/topics', [$forum, 'topics']);
$router->get('/api/v1/forum/topics/{id}', [$forum, 'topic']);
$router->post('/api/v1/forum/topics/{id}/reply', [$forum, 'reply']);

$calendar = new CalendarApiController($app);
$router->get('/api/v1/calendar/events', [$calendar, 'index']);
$router->get('/api/v1/calendar/events/{id}', [$calendar, 'show']);

// Stage 10, second API slice — extending the proven pattern above to the
// next tier of read-heavy modules. Reads only this slice, same "prove the
// pattern, extend later" discipline; no new write endpoint since
// ForumApiController::reply() already proved that shape.
$wiki = new WikiApiController($app);
$router->get('/api/v1/wiki', [$wiki, 'index']);
$router->get('/api/v1/wiki/{slug}', [$wiki, 'show']);

$downloads = new DownloadsApiController($app);
$router->get('/api/v1/downloads', [$downloads, 'index']);
$router->get('/api/v1/downloads/{id}', [$downloads, 'show']);

$gallery = new GalleryApiController($app);
$router->get('/api/v1/gallery/albums', [$gallery, 'albums']);
$router->get('/api/v1/gallery/albums/{id}/photos', [$gallery, 'photos']);

$router->get('/api-docs', static function (\Stratum\Core\Request $request) use ($app): \Stratum\Core\Response {
    $content = $app->templates->render('api', 'docs', ['baseUrl' => $request->baseUrl()]);

    return \Stratum\Core\Response::html($app->renderPage($content, $request));
});

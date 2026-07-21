<?php

declare(strict_types=1);

use Stratum\Api\ActivityApiController;
use Stratum\Api\ArticlesApiController;
use Stratum\Api\BookmarksApiController;
use Stratum\Api\CalendarApiController;
use Stratum\Api\ChatApiController;
use Stratum\Api\CommentsApiController;
use Stratum\Api\DownloadsApiController;
use Stratum\Api\ForumApiController;
use Stratum\Api\GalleryApiController;
use Stratum\Api\MessagesApiController;
use Stratum\Api\RatingsApiController;
use Stratum\Api\TagsApiController;
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

// Stage 10, fourth API slice — cross-content features (tags, comments,
// ratings, activity) rather than another whole module. Two new write
// endpoints this slice (comment create, rating create), both direct
// mirrors of their existing web controllers' exact logic.
$tags = new TagsApiController($app);
$router->get('/api/v1/tags', [$tags, 'index']);
$router->get('/api/v1/tags/{slug}', [$tags, 'show']);

$comments = new CommentsApiController($app);
$router->get('/api/v1/comments/{type}/{id}', [$comments, 'index']);
$router->post('/api/v1/comments/{type}/{id}', [$comments, 'create']);

$ratings = new RatingsApiController($app);
$router->get('/api/v1/ratings/{type}/{id}', [$ratings, 'show']);
$router->post('/api/v1/ratings/{type}/{id}', [$ratings, 'rate']);

$activity = new ActivityApiController($app);
$router->get('/api/v1/activity', [$activity, 'index']);

// Stage 10, fifth API slice — chat. Unlike every other read endpoint in
// this API, viewing messages needs a Bearer token (chat has no
// guest-visible view, only room discovery does) — see
// ChatApiController::messages()'s own docblock for why.
$chat = new ChatApiController($app);
$router->get('/api/v1/chat/rooms', [$chat, 'rooms']);
$router->get('/api/v1/chat/rooms/{id}/messages', [$chat, 'messages']);
$router->post('/api/v1/chat/rooms/{id}/messages', [$chat, 'postMessage']);

// Stage 10, sixth API slice — the two fully private resources: bookmarks
// (a user's own saved items) and direct messages (a user's own
// conversations). Every action here requires a Bearer token and is scoped
// to the caller; there is no public read path on either resource, unlike
// every earlier slice.
$bookmarks = new BookmarksApiController($app);
$router->get('/api/v1/bookmarks', [$bookmarks, 'index']);
$router->post('/api/v1/bookmarks/{type}/{id}', [$bookmarks, 'toggle']);

$messages = new MessagesApiController($app);
$router->get('/api/v1/messages/conversations', [$messages, 'conversations']);
$router->get('/api/v1/messages/conversations/{id}', [$messages, 'show']);
$router->post('/api/v1/messages/conversations/{id}/reply', [$messages, 'reply']);
$router->post('/api/v1/messages/start', [$messages, 'start']);

$router->get('/api-docs', static function (\Stratum\Core\Request $request) use ($app): \Stratum\Core\Response {
    $content = $app->templates->render('api', 'docs', ['baseUrl' => $request->baseUrl()]);

    return \Stratum\Core\Response::html($app->renderPage($content, $request));
});

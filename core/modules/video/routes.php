<?php

declare(strict_types=1);

use Stratum\Modules\Video\VideoAdminController;
use Stratum\Modules\Video\VideoController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$video = new VideoController($app);
$admin = new VideoAdminController($app);

$router->get('/videos', [$video, 'index']);

// Literal path registered before the {id}-pattern route below, same
// ordering discipline as every other module's routes.php.
$router->get('/videos/create', [$video, 'showCreate']);
$router->post('/videos/create', [$video, 'create']);

$router->get('/videos/playlists', [$video, 'playlistIndex']);
$router->get('/videos/playlists/create', [$video, 'showCreatePlaylist']);
$router->post('/videos/playlists/create', [$video, 'createPlaylist']);
$router->get('/videos/playlists/{slug}', [$video, 'playlistShow']);
$router->post('/videos/playlists/{id}/delete', [$video, 'deletePlaylist']);
$router->post('/videos/playlists/{id}/videos', [$video, 'addToPlaylist']);
$router->post('/videos/playlists/{id}/videos/{itemId}/remove', [$video, 'removeFromPlaylist']);
$router->post('/videos/playlists/{id}/videos/{itemId}/move', [$video, 'movePlaylistItem']);

$router->get('/videos/{id}', [$video, 'show']);
$router->get('/videos/{id}/stream', [$video, 'stream']);

$router->get('/admin/video', [$admin, 'index']);
$router->post('/admin/video/categories', [$admin, 'createCategory']);
$router->post('/admin/video/{id}/delete', [$admin, 'deleteVideo']);

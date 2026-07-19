<?php

declare(strict_types=1);

use Stratum\Modules\Gallery\GalleryController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$gallery = new GalleryController($app);

$router->get('/gallery', [$gallery, 'index']);

// Literal path registered before the {id}-pattern route below, same
// ordering discipline as every other module's routes.php.
$router->get('/gallery/create', [$gallery, 'showCreate']);
$router->post('/gallery/create', [$gallery, 'create']);

$router->get('/gallery/albums/{id}', [$gallery, 'album']);
$router->post('/gallery/albums/{id}/photos', [$gallery, 'addPhotos']);
$router->post('/gallery/albums/{id}/delete', [$gallery, 'deleteAlbum']);

$router->get('/gallery/photos/{id}', [$gallery, 'photo']);
$router->get('/gallery/photos/{id}/image', [$gallery, 'image']);
$router->get('/gallery/photos/{id}/thumbnail', [$gallery, 'thumbnail']);
$router->post('/gallery/photos/{id}/like', [$gallery, 'toggleLike']);
$router->post('/gallery/photos/{id}/delete', [$gallery, 'deletePhoto']);

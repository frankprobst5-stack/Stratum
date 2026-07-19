<?php

declare(strict_types=1);

use Stratum\Modules\Classifieds\ClassifiedsAdminController;
use Stratum\Modules\Classifieds\ClassifiedsController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$classifieds = new ClassifiedsController($app);
$admin = new ClassifiedsAdminController($app);

$router->get('/classifieds', [$classifieds, 'index']);

// Literal path registered before the {id}-pattern route below, same
// ordering discipline as every other module's routes.php.
$router->get('/classifieds/create', [$classifieds, 'showCreate']);
$router->post('/classifieds/create', [$classifieds, 'create']);

$router->get('/classifieds/listings/{id}', [$classifieds, 'listing']);
$router->post('/classifieds/listings/{id}/sold', [$classifieds, 'markSold']);
$router->post('/classifieds/listings/{id}/delete', [$classifieds, 'delete']);
$router->get('/classifieds/listings/{id}/image', [$classifieds, 'image']);
$router->get('/classifieds/listings/{id}/thumbnail', [$classifieds, 'thumbnail']);

$router->get('/admin/classifieds', [$admin, 'index']);
$router->post('/admin/classifieds/categories', [$admin, 'createCategory']);

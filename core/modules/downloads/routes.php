<?php

declare(strict_types=1);

use Stratum\Modules\Downloads\DownloadsAdminController;
use Stratum\Modules\Downloads\DownloadsController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$downloads = new DownloadsController($app);
$admin = new DownloadsAdminController($app);

$router->get('/downloads', [$downloads, 'index']);

// Literal path registered before the {id}-pattern route below, same
// ordering discipline as every other module's routes.php.
$router->get('/downloads/create', [$downloads, 'showCreate']);
$router->post('/downloads/create', [$downloads, 'create']);

$router->get('/downloads/files/{id}', [$downloads, 'show']);
$router->post('/downloads/files/{id}/versions', [$downloads, 'addVersion']);
$router->get('/downloads/files/{id}/download', [$downloads, 'download']);
$router->get('/downloads/files/{id}/versions/{versionId}/download', [$downloads, 'downloadVersion']);
$router->post('/downloads/files/{id}/mirrors', [$downloads, 'addMirror']);
$router->post('/downloads/files/{id}/mirrors/{mirrorId}/delete', [$downloads, 'deleteMirror']);

$router->get('/admin/downloads', [$admin, 'index']);
$router->post('/admin/downloads/categories', [$admin, 'createCategory']);
$router->post('/admin/downloads/files/{id}/delete', [$admin, 'deleteFile']);

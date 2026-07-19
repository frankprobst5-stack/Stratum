<?php

declare(strict_types=1);

use Stratum\Modules\OrgSpaces\OrgCalendarController;
use Stratum\Modules\OrgSpaces\OrgFileController;
use Stratum\Modules\OrgSpaces\OrgForumController;
use Stratum\Modules\OrgSpaces\OrgGalleryController;
use Stratum\Modules\OrgSpaces\OrgSpacesAdminController;
use Stratum\Modules\OrgSpaces\OrgSpacesController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$orgSpaces = new OrgSpacesController($app);
$admin = new OrgSpacesAdminController($app);
$orgForum = new OrgForumController($app);
$orgCalendar = new OrgCalendarController($app);
$orgFiles = new OrgFileController($app);
$orgGallery = new OrgGalleryController($app);

$router->get('/organizations', [$orgSpaces, 'index']);
$router->get('/organizations/{slug}', [$orgSpaces, 'show']);
$router->post('/organizations/{slug}/members', [$orgSpaces, 'addMember']);
$router->post('/organizations/{slug}/members/{userId}/update', [$orgSpaces, 'updateMember']);
$router->post('/organizations/{slug}/members/{userId}/remove', [$orgSpaces, 'removeMember']);
$router->post('/organizations/{slug}/announcements', [$orgSpaces, 'postAnnouncement']);
$router->post('/organizations/{slug}/announcements/{id}/delete', [$orgSpaces, 'deleteAnnouncement']);

$router->get('/organizations/{slug}/forum', [$orgForum, 'index']);
$router->post('/organizations/{slug}/forum/topics', [$orgForum, 'createTopic']);
$router->get('/organizations/{slug}/forum/topics/{id}', [$orgForum, 'topic']);
$router->post('/organizations/{slug}/forum/topics/{id}/reply', [$orgForum, 'reply']);
$router->post('/organizations/{slug}/forum/topics/{id}/lock', [$orgForum, 'lock']);
$router->post('/organizations/{slug}/forum/topics/{id}/unlock', [$orgForum, 'unlock']);
$router->post('/organizations/{slug}/forum/topics/{id}/delete', [$orgForum, 'deleteTopic']);
$router->post('/organizations/{slug}/forum/posts/{id}/delete', [$orgForum, 'deletePost']);

$router->get('/organizations/{slug}/calendar', [$orgCalendar, 'index']);
$router->post('/organizations/{slug}/calendar/events', [$orgCalendar, 'createEvent']);
$router->get('/organizations/{slug}/calendar/events/{id}', [$orgCalendar, 'event']);
$router->post('/organizations/{slug}/calendar/events/{id}/delete', [$orgCalendar, 'deleteEvent']);

$router->get('/organizations/{slug}/files', [$orgFiles, 'index']);
$router->post('/organizations/{slug}/files', [$orgFiles, 'upload']);
$router->get('/organizations/{slug}/files/{id}/download', [$orgFiles, 'download']);
$router->post('/organizations/{slug}/files/{id}/delete', [$orgFiles, 'delete']);

$router->get('/organizations/{slug}/gallery', [$orgGallery, 'index']);
$router->post('/organizations/{slug}/gallery/albums', [$orgGallery, 'createAlbum']);
$router->get('/organizations/{slug}/gallery/albums/{id}', [$orgGallery, 'album']);
$router->post('/organizations/{slug}/gallery/albums/{id}/delete', [$orgGallery, 'deleteAlbum']);
$router->get('/organizations/{slug}/gallery/photos/{id}/image', [$orgGallery, 'image']);
$router->get('/organizations/{slug}/gallery/photos/{id}/thumbnail', [$orgGallery, 'thumbnail']);
$router->post('/organizations/{slug}/gallery/photos/{id}/delete', [$orgGallery, 'deletePhoto']);

$router->get('/admin/org_spaces', [$admin, 'index']);
$router->post('/admin/org_spaces/create', [$admin, 'create']);
$router->post('/admin/org_spaces/{id}/toggle-active', [$admin, 'toggleActive']);

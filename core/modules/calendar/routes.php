<?php

declare(strict_types=1);

use Stratum\Modules\Calendar\CalendarAdminController;
use Stratum\Modules\Calendar\CalendarController;

/**
 * @var \Stratum\Core\Router $router
 * @var \Stratum\Core\App $app
 */

$calendar = new CalendarController($app);
$admin = new CalendarAdminController($app);

$router->get('/calendar', [$calendar, 'index']);

// Literal path registered before the {calendarSlug}-pattern route below,
// or "create" would be swallowed as a :calendarSlug value — same ordering
// discipline as every other module's routes.php.
$router->get('/calendar/create', [$calendar, 'showCreate']);
$router->post('/calendar/create', [$calendar, 'create']);

$router->get('/calendar/events/{id}', [$calendar, 'eventShow']);
$router->post('/calendar/events/{id}/rsvp', [$calendar, 'rsvp']);
$router->post('/calendar/events/{id}/delete', [$calendar, 'deleteEvent']);
$router->post('/calendar/events/{id}/attendance', [$calendar, 'checkIn']);
$router->post('/calendar/events/{id}/attendance/{userId}/remove', [$calendar, 'removeCheckIn']);

$router->get('/calendar/{calendarSlug}', [$calendar, 'calendarShow']);

$router->get('/admin/calendar', [$admin, 'index']);
$router->post('/admin/calendar/calendars', [$admin, 'createCalendar']);

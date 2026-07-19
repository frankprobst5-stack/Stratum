<?php

declare(strict_types=1);

namespace Stratum\Modules\Calendar;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class CalendarAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('calendar.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('calendar', 'admin-index', [
            'calendars' => (new CalendarService($this->app->db))->listCalendars(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createCalendar(Request $request): Response
    {
        if (($guard = $this->guard('calendar.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new CalendarService($this->app->db))->createCalendar($name, (string) $request->input('description', ''));
        }

        return Response::redirect('/admin/calendar');
    }
}

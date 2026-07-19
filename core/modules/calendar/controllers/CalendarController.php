<?php

declare(strict_types=1);

namespace Stratum\Modules\Calendar;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\SeoService;
use Stratum\Modules\Comments\CommentService;
use Stratum\Modules\Users\AuthService;

final class CalendarController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $calendar = new CalendarService($this->app->db);

        $content = $this->app->templates->render('calendar', 'index', [
            'events' => $calendar->listUpcomingEvents(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function calendarShow(Request $request): Response
    {
        $service = new CalendarService($this->app->db);
        $cal = $service->findCalendarBySlug((string) $request->param('calendarSlug', ''));
        if ($cal === null) {
            return Response::notFound();
        }

        $content = $this->app->templates->render('calendar', 'calendar', [
            'calendar' => $cal,
            'events' => $service->listUpcomingEvents((int) $cal['id']),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function eventShow(Request $request): Response
    {
        $service = new CalendarService($this->app->db);
        $event = $service->findEvent((int) $request->param('id', '0'));
        if ($event === null) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $comments = new CommentService($this->app->db);

        $attendees = array_map(
            fn (array $r): array => $r + ['username' => $this->username($authors, $r['user_id'])],
            $service->listRsvps((int) $event['id'])
        );

        $attendance = array_map(
            fn (array $r): array => $r + ['username' => $this->username($authors, $r['user_id'])],
            $service->listAttendance((int) $event['id'])
        );

        $commentRows = array_map(
            fn (array $c): array => $c + ['authorName' => $this->username($authors, (int) $c['user_id'])],
            $comments->listFor('calendar_event', (int) $event['id'])
        );

        $currentUser = $this->app->auth->user();

        $content = $this->app->templates->render('calendar', 'event', [
            'event' => $event,
            'attendees' => $attendees,
            'attendance' => $attendance,
            'myRsvp' => $currentUser !== null ? $service->myRsvp((int) $event['id'], (int) $currentUser['id']) : null,
            'comments' => $commentRows,
            'canRsvp' => $this->app->auth->can('calendar.rsvp'),
            'canManage' => $this->app->auth->can('calendar.manage'),
            'canComment' => $this->app->auth->can('comments.create'),
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $event['title'],
            'description' => (new SeoService())->excerpt((string) ($event['description'] ?? '')),
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->requireCapability('calendar.create_event')) !== null) {
            return $guard;
        }

        $service = new CalendarService($this->app->db);

        $content = $this->app->templates->render('calendar', 'form', [
            'calendars' => $service->listCalendars(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->requireCapability('calendar.create_event')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $calendarId = (int) $request->input('calendar_id', '0');
        $title = trim((string) $request->input('title', ''));
        $startsAt = (string) $request->input('starts_at', '');

        if ($calendarId <= 0 || $title === '' || $startsAt === '') {
            return Response::redirect('/calendar/create');
        }

        $service = new CalendarService($this->app->db);
        $user = $this->app->auth->user();

        $ids = $service->createEvent(
            $calendarId,
            (int) $user['id'],
            $title,
            (string) $request->input('description', ''),
            (string) $request->input('location', ''),
            $startsAt,
            $request->input('ends_at', '') ?: null,
            $request->input('is_all_day') === '1',
            (string) $request->input('recurrence_type', 'none'),
            (int) $request->input('occurrence_count', '1')
        );

        return Response::redirect('/calendar/events/' . $ids[0]);
    }

    public function rsvp(Request $request): Response
    {
        if (($guard = $this->requireCapability('calendar.rsvp')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $eventId = (int) $request->param('id', '0');
        $status = (string) $request->input('status', '');

        if (in_array($status, ['going', 'maybe', 'declined'], true)) {
            $user = $this->app->auth->user();
            (new CalendarService($this->app->db))->setRsvp($eventId, (int) $user['id'], $status);
        }

        return Response::redirect('/calendar/events/' . $eventId);
    }

    public function deleteEvent(Request $request): Response
    {
        if (($guard = $this->requireCapability('calendar.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new CalendarService($this->app->db);
        $event = $service->findEvent((int) $request->param('id', '0'));
        if ($event === null) {
            return Response::notFound();
        }

        $service->softDeleteEvent((int) $event['id']);

        return Response::redirect('/calendar/' . $event['calendar_slug']);
    }

    /**
     * Post-event roll call, distinct from RSVP — "who actually showed up"
     * rather than "who said they were coming." Takes a username (not a
     * user_id select) so an organizer can check in a walk-in who never
     * RSVP'd, not just tick off the existing Going list.
     */
    public function checkIn(Request $request): Response
    {
        if (($guard = $this->requireCapability('calendar.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $eventId = (int) $request->param('id', '0');
        $username = trim((string) $request->input('username', ''));

        if ($username !== '') {
            $member = (new AuthService($this->app->db))->findByUsername($username);
            if ($member !== null) {
                $admin = $this->app->auth->user();
                (new CalendarService($this->app->db))->checkIn($eventId, (int) $member['id'], (int) $admin['id']);
            }
        }

        return Response::redirect('/calendar/events/' . $eventId);
    }

    public function removeCheckIn(Request $request): Response
    {
        if (($guard = $this->requireCapability('calendar.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $eventId = (int) $request->param('id', '0');
        (new CalendarService($this->app->db))->removeCheckIn($eventId, (int) $request->param('userId', '0'));

        return Response::redirect('/calendar/events/' . $eventId);
    }

    private function requireCapability(string $capability): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can($capability)) {
            return Response::forbidden();
        }

        return null;
    }

    private function username(AuthService $authors, int $userId): string
    {
        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }
}

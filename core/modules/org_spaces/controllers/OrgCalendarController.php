<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class OrgCalendarController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'])) !== null) {
            return $guard;
        }

        $calendar = new OrgCalendarService($this->app->db);

        $content = $this->app->templates->render('org_spaces', 'calendar-index', [
            'org' => $org,
            'events' => $calendar->listUpcoming((int) $org['id']),
            'canManage' => $this->canManageOrg((int) $org['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createEvent(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'], verifyCsrf: true, request: $request)) !== null) {
            return $guard;
        }

        $title = trim((string) $request->input('title', ''));
        $startsAt = (string) $request->input('starts_at', '');
        if ($title === '' || $startsAt === '') {
            return Response::redirect('/organizations/' . $org['slug'] . '/calendar');
        }

        $user = $this->app->auth->user();
        $calendar = new OrgCalendarService($this->app->db);
        $eventId = $calendar->createEvent(
            (int) $org['id'],
            (int) $user['id'],
            $title,
            (string) $request->input('description', ''),
            (string) $request->input('location', ''),
            $startsAt,
            (string) $request->input('ends_at', '')
        );

        return Response::redirect('/organizations/' . $org['slug'] . '/calendar/events/' . $eventId);
    }

    public function event(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'])) !== null) {
            return $guard;
        }

        $calendar = new OrgCalendarService($this->app->db);
        $event = $calendar->findEvent((int) $request->param('id', '0'));
        if ($event === null || (int) $event['org_id'] !== (int) $org['id']) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $authorRow = $authors->findById((int) $event['author_id']);

        $content = $this->app->templates->render('org_spaces', 'calendar-event', [
            'org' => $org,
            'event' => $event,
            'authorName' => $authorRow['username'] ?? 'Unknown',
            'canManage' => $this->canManageOrg((int) $org['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function deleteEvent(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $calendar = new OrgCalendarService($this->app->db);
        $event = $calendar->findEvent((int) $request->param('id', '0'));
        if ($event !== null && (int) $event['org_id'] === (int) $org['id']) {
            $calendar->softDeleteEvent((int) $event['id']);
        }

        return Response::redirect('/organizations/' . $org['slug'] . '/calendar');
    }

    /** @return array<string, mixed>|Response */
    private function requireActiveOrg(Request $request): array|Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null || !$org['is_active']) {
            return Response::notFound();
        }

        return $org;
    }

    private function requireMember(int $orgId, bool $verifyCsrf = false, ?Request $request = null): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->app->auth->user();
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        if (!$service->isMember((int) $user['id'], $orgId) && !$this->canManageOrg($orgId)) {
            return Response::forbidden();
        }

        if ($verifyCsrf && !$this->app->session->verifyCsrf($request?->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        return null;
    }

    private function requireManage(int $orgId, Request $request): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->canManageOrg($orgId)) {
            return Response::forbidden();
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        return null;
    }

    private function canManageOrg(int $orgId): bool
    {
        return $this->app->auth->can('org_spaces.moderate', 'org', $orgId);
    }
}

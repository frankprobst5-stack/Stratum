<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\App;
use Stratum\Core\BBCodeParser;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class OrgSpacesController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);

        $content = $this->app->templates->render('org_spaces', 'index', [
            'orgs' => $service->listOrgs(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function show(Request $request): Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null || !$org['is_active']) {
            return Response::notFound();
        }

        $currentUser = $this->app->auth->user();
        $userId = $currentUser !== null ? (int) $currentUser['id'] : null;
        $canManage = $this->canManageOrg((int) $org['id']);
        $isMember = $userId !== null && $service->isMember($userId, (int) $org['id']);

        $parser = new BBCodeParser();
        $authors = new AuthService($this->app->db);
        $announcements = array_map(
            fn (array $a): array => $a + [
                'renderedBody' => $parser->render($a['body']),
                'authorName' => $this->authorName($authors, $a['author_id'] !== null ? (int) $a['author_id'] : null),
            ],
            $service->listAnnouncements((int) $org['id'])
        );

        $content = $this->app->templates->render('org_spaces', 'show', [
            'org' => $org,
            'renderedDescription' => $org['description'] !== null ? $parser->render($org['description']) : null,
            'officers' => $service->listOfficers((int) $org['id']),
            'roster' => $canManage || $isMember ? $service->listRoster((int) $org['id']) : [],
            'canSeeRoster' => $canManage || $isMember,
            'announcements' => $announcements,
            'canManage' => $canManage,
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function addMember(Request $request): Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null) {
            return Response::notFound();
        }

        if (($guard = $this->guardManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $username = trim((string) $request->input('username', ''));
        if ($username !== '') {
            $user = (new AuthService($this->app->db))->findByUsername($username);
            if ($user !== null) {
                $service->addMember(
                    (int) $org['id'],
                    (int) $user['id'],
                    (string) $request->input('title', ''),
                    $request->input('is_officer') === '1'
                );
            }
        }

        return Response::redirect('/organizations/' . $org['slug']);
    }

    public function updateMember(Request $request): Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null) {
            return Response::notFound();
        }

        if (($guard = $this->guardManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $service->updateMember(
            (int) $org['id'],
            (int) $request->param('userId', '0'),
            (string) $request->input('title', ''),
            $request->input('is_officer') === '1'
        );

        return Response::redirect('/organizations/' . $org['slug']);
    }

    public function removeMember(Request $request): Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null) {
            return Response::notFound();
        }

        if (($guard = $this->guardManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $service->removeMember((int) $org['id'], (int) $request->param('userId', '0'));

        return Response::redirect('/organizations/' . $org['slug']);
    }

    public function postAnnouncement(Request $request): Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null) {
            return Response::notFound();
        }

        if (($guard = $this->guardManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        if ($title !== '' && $body !== '') {
            $currentUser = $this->app->auth->user();
            $service->postAnnouncement((int) $org['id'], (int) $currentUser['id'], $title, $body);

            // Whole roster; the posting officer is excluded by the
            // listener's actor rule, non-roster members were never included.
            $this->app->notify([
                'user_id' => array_map(
                    'intval',
                    array_column($service->listRoster((int) $org['id']), 'user_id')
                ),
                'actor_id' => (int) $currentUser['id'],
                'type' => 'org.announcement',
                'message' => 'New announcement in ' . $org['name'] . ': "' . $title . '"',
                'url' => '/organizations/' . $org['slug'],
            ]);
        }

        return Response::redirect('/organizations/' . $org['slug']);
    }

    public function deleteAnnouncement(Request $request): Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null) {
            return Response::notFound();
        }

        if (($guard = $this->guardManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $announcement = $service->findAnnouncement((int) $request->param('id', '0'));
        if ($announcement !== null && (int) $announcement['org_id'] === (int) $org['id']) {
            $service->deleteAnnouncement((int) $announcement['id']);
        }

        return Response::redirect('/organizations/' . $org['slug']);
    }

    private function guardManage(int $orgId, Request $request): ?Response
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

    /**
     * A single scoped capability check — site-wide org_spaces.moderate
     * (admin/founder) satisfies this for any org via PermissionEngine's
     * "site-wide grant matches any scope" rule, and per-org officers hold
     * org_spaces.moderate scoped to just their own org. See the retrofit
     * plan's Decisions section.
     */
    private function canManageOrg(int $orgId): bool
    {
        return $this->app->auth->can('org_spaces.moderate', 'org', $orgId);
    }

    private function authorName(AuthService $authors, ?int $userId): string
    {
        if ($userId === null) {
            return 'Unknown';
        }

        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }
}

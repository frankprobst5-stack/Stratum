<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\AccountMergeService;
use Stratum\Core\MemberNoteService;
use Stratum\Core\Request;
use Stratum\Core\ReputationService;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;
use Stratum\Modules\Users\BadgeService;

final class UsersController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        $authService = new AuthService($this->app->db);
        $users = $authService->listUsers();

        foreach ($users as &$user) {
            $user['roleIds'] = $this->app->permissions->rolesForUser((int) $user['id']);
        }
        unset($user);

        $content = $this->app->templates->render('admin', 'users', [
            'users' => $users,
            'roles' => $this->app->permissions->listRoles(),
            'deletedUsers' => $authService->listDeletedUsers(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'users-create', [
            'roles' => $this->app->permissions->listRoles(),
            'csrfToken' => $this->app->session->csrfToken(),
            'error' => null,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $username = (string) $request->input('username', '');
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');
        $roleIds = array_map('intval', array_keys($request->inputArray('roles')));

        $authService = new AuthService($this->app->db);

        $error = null;
        if ($username === '' || $email === '' || $password === '') {
            $error = 'Username, email, and password are all required.';
        } elseif (strlen($password) < 12) {
            $error = 'Password must be at least 12 characters.';
        } elseif ($authService->usernameOrEmailExists($username, $email)) {
            $error = 'That username or email is already taken.';
        }

        if ($error !== null) {
            $content = $this->app->templates->render('admin', 'users-create', [
                'roles' => $this->app->permissions->listRoles(),
                'csrfToken' => $this->app->session->csrfToken(),
                'error' => $error,
            ]);

            return Response::html($this->app->renderPage($content, $request), 422);
        }

        $userId = $authService->createUser($username, $email, $password);
        $this->app->permissions->setRolesForUser((int) $userId, $roleIds);

        return Response::redirect('/admin/users');
    }

    public function updateRoles(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $userId = (int) $request->param('id', '0');
        $roleIds = array_map('intval', array_keys($request->inputArray('roles')));

        $this->app->permissions->setRolesForUser($userId, $roleIds);

        return Response::redirect('/admin/users');
    }

    public function show(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        $authService = new AuthService($this->app->db);
        $userId = (int) $request->param('id', '0');
        $user = $authService->findById($userId);
        if ($user === null) {
            return Response::notFound();
        }

        $notes = array_map(
            fn (array $n): array => $n + ['authorName' => $this->authorName($authService, (int) $n['author_id'])],
            (new MemberNoteService($this->app->db))->listFor($userId)
        );

        $badgeService = new BadgeService($this->app->db);
        $memberBadges = $badgeService->listForUser($userId);
        $memberBadgeIds = array_column($memberBadges, 'id');

        $content = $this->app->templates->render('admin', 'user-detail', [
            'user' => $user,
            'roleIds' => $this->app->permissions->rolesForUser($userId),
            'roles' => $this->app->permissions->listRoles(),
            'notes' => $notes,
            'memberBadges' => $memberBadges,
            'allBadges' => array_filter(
                $badgeService->listBadges(),
                static fn (array $b): bool => !in_array($b['id'], $memberBadgeIds, true)
            ),
            'csrfToken' => $this->app->session->csrfToken(),
            'deleteError' => $request->query('error'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function awardBadge(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $userId = (int) $request->param('id', '0');
        $badgeId = (int) $request->input('badge_id', '0');

        if ($badgeId > 0) {
            $admin = $this->app->auth->user();
            (new BadgeService($this->app->db))->award($userId, $badgeId, (int) $admin['id']);
        }

        return Response::redirect('/admin/users/' . $userId);
    }

    public function revokeBadge(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $userId = (int) $request->param('id', '0');
        $badgeId = (int) $request->param('badgeId', '0');

        (new BadgeService($this->app->db))->revoke($userId, $badgeId);

        return Response::redirect('/admin/users/' . $userId);
    }

    public function addNote(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $userId = (int) $request->param('id', '0');
        $body = trim((string) $request->input('body', ''));

        if ($body !== '' && (new AuthService($this->app->db))->findById($userId) !== null) {
            $admin = $this->app->auth->user();
            (new MemberNoteService($this->app->db))->add($userId, (int) $admin['id'], $body);
        }

        return Response::redirect('/admin/users/' . $userId);
    }

    public function deleteNote(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $userId = (int) $request->param('id', '0');
        $noteId = (int) $request->param('noteId', '0');

        (new MemberNoteService($this->app->db))->delete($userId, $noteId);

        return Response::redirect('/admin/users/' . $userId);
    }

    /** Admin-initiated deletion — same soft-delete + last-admin guard the self-service /profile/delete flow uses, no password re-entry needed since this is already gated by users.manage + CSRF. */
    public function deleteAccount(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $userId = (int) $request->param('id', '0');
        $authService = new AuthService($this->app->db);

        if ($authService->isLastAdmin($userId)) {
            return Response::redirect('/admin/users/' . $userId . '?error=' . rawurlencode('Cannot delete the site\'s last admin.'));
        }

        $authService->softDeleteAccount($userId);

        return Response::redirect('/admin/users');
    }

    public function restoreAccount(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new AuthService($this->app->db))->restoreAccount((int) $request->param('id', '0'));

        return Response::redirect('/admin/users');
    }

    public function showMerge(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'users-merge', [
            'users' => (new AuthService($this->app->db))->listUsers(),
            'csrfToken' => $this->app->session->csrfToken(),
            'error' => $request->query('error'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /**
     * Merges a duplicate account into a canonical one — see
     * AccountMergeService for the full reassignment scope. Refuses a
     * no-op self-merge and refuses merging away the site's last admin,
     * same guard deleteAccount() already uses since this ends in
     * source being soft-deleted exactly like a normal deletion.
     */
    public function merge(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $sourceId = (int) $request->input('source_id', '0');
        $targetId = (int) $request->input('target_id', '0');
        $authService = new AuthService($this->app->db);

        $error = null;
        if ($sourceId === $targetId) {
            $error = 'Choose two different accounts to merge.';
        } elseif ($authService->findById($sourceId) === null || $authService->findById($targetId) === null) {
            $error = 'One of the selected accounts no longer exists.';
        } elseif ($authService->isLastAdmin($sourceId)) {
            $error = "Cannot merge away the site's last admin.";
        }

        if ($error !== null) {
            return Response::redirect('/admin/users/merge?error=' . rawurlencode($error));
        }

        $merger = new AccountMergeService($this->app->db, new ReputationService($this->app));
        $merger->merge($sourceId, $targetId);

        return Response::redirect('/admin/users/' . $targetId);
    }

    private function authorName(AuthService $authors, int $userId): string
    {
        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }
}

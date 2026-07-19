<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\AccountExportService;
use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class ProfileController
{
    public function __construct(private readonly App $app)
    {
    }

    public function show(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->app->auth->user();
        $authService = new AuthService($this->app->db);

        $content = $this->app->templates->render('users', 'profile', [
            'user' => $user,
            'rankName' => $authService->rankName($user['rank_id'] !== null ? (int) $user['rank_id'] : null),
            'csrfToken' => $this->app->session->csrfToken(),
            'saved' => $request->query('saved') === '1',
            'deleteError' => $request->query('delete_error'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /** A JSON download of the member's own account fields plus a manifest of content they've authored — see AccountExportService. */
    public function export(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->app->auth->user();
        $data = (new AccountExportService($this->app->db, $this->app->modules))->export($user);
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return Response::file($json, 'application/json', $user['username'] . '-stratum-export.json');
    }

    /**
     * Self-service account deletion — requires re-entering your password
     * (the same confirmation weight a destructive action like this
     * deserves, distinct from every other action on this page which only
     * needs a valid session + CSRF token) and refuses if you're the
     * site's last admin. Soft-delete only, your content stays exactly
     * where it is — see AuthService::softDeleteAccount().
     */
    public function delete(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $user = $this->app->auth->user();
        $authService = new AuthService($this->app->db);
        $password = (string) $request->input('password', '');

        if ($authService->findByCredentials($user['username'], $password) === null) {
            return Response::redirect('/profile?delete_error=' . rawurlencode('Incorrect password.'));
        }

        if ($authService->isLastAdmin((int) $user['id'])) {
            return Response::redirect('/profile?delete_error=' . rawurlencode('You are the only admin — promote another member to admin before deleting this account.'));
        }

        $authService->softDeleteAccount((int) $user['id']);
        $this->app->auth->logout();

        return Response::redirect('/');
    }

    public function update(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $user = $this->app->auth->user();
        $authService = new AuthService($this->app->db);
        $authService->updateProfile(
            (int) $user['id'],
            (string) $request->input('about_me', ''),
            (string) $request->input('avatar_url', ''),
            trim((string) $request->input('signature', '')),
            (string) $request->input('banner_url', '')
        );

        return Response::redirect('/profile?saved=1');
    }
}

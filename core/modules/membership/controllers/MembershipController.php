<?php

declare(strict_types=1);

namespace Stratum\Modules\Membership;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class MembershipController
{
    public function __construct(private readonly App $app)
    {
    }

    public function showRegister(Request $request): Response
    {
        if ($this->app->auth->check()) {
            return Response::redirect('/');
        }

        $content = $this->app->templates->render('membership', 'register', [
            'fields' => (new MembershipFieldService($this->app->db))->listFields(),
            'csrfToken' => $this->app->session->csrfToken(),
            'error' => null,
            'values' => [],
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function register(Request $request): Response
    {
        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $fieldService = new MembershipFieldService($this->app->db);
        $applicationService = new MembershipApplicationService($this->app->db);
        $authService = new AuthService($this->app->db);
        $fields = $fieldService->listFields();

        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $passwordConfirm = (string) $request->input('password_confirm', '');

        $answers = [];
        foreach ($fields as $field) {
            $fieldKey = 'field_' . $field['id'];
            $answers[(string) $field['id']] = $field['field_type'] === 'checkbox'
                ? ($request->input($fieldKey) !== null ? '1' : '')
                : trim((string) $request->input($fieldKey, ''));
        }

        $error = $this->validate($username, $email, $password, $passwordConfirm, $fields, $answers, $applicationService, $authService);

        if ($error !== null) {
            $content = $this->app->templates->render('membership', 'register', [
                'fields' => $fields,
                'csrfToken' => $this->app->session->csrfToken(),
                'error' => $error,
                'values' => ['username' => $username, 'email' => $email],
            ]);

            return Response::html($this->app->renderPage($content, $request), 422);
        }

        $applicationService->submitApplication(
            $username,
            $email,
            password_hash($password, PASSWORD_ARGON2ID),
            $answers
        );

        $content = $this->app->templates->render('membership', 'thanks', []);

        return Response::html($this->app->renderPage($content, $request));
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     */
    private function validate(
        string $username,
        string $email,
        string $password,
        string $passwordConfirm,
        array $fields,
        array $answers,
        MembershipApplicationService $applicationService,
        AuthService $authService
    ): ?string {
        if ($username === '' || $email === '' || $password === '') {
            return 'Username, email, and password are all required.';
        }

        if (strlen($password) < 12) {
            return 'Password must be at least 12 characters.';
        }

        if ($password !== $passwordConfirm) {
            return 'Passwords did not match.';
        }

        if ($applicationService->isUsernameOrEmailTaken($username, $email, $authService)) {
            return 'That username or email is already taken or has a pending application.';
        }

        foreach ($fields as $field) {
            if (!$field['is_required']) {
                continue;
            }

            $answer = $answers[(string) $field['id']] ?? '';
            if ($answer === '' || $answer === '0') {
                return "\"{$field['label']}\" is required.";
            }
        }

        return null;
    }
}

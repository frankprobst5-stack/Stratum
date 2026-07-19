<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class AuthController
{
    public function __construct(private readonly App $app)
    {
    }

    public function showLogin(Request $request): Response
    {
        if ($this->app->auth->check()) {
            return Response::redirect('/');
        }

        $content = $this->app->templates->render('users', 'login', [
            'error' => null,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function login(Request $request): Response
    {
        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $login = (string) $request->input('login', '');
        $password = (string) $request->input('password', '');

        if ($this->app->auth->attempt($login, $password, $request->ip())) {
            return Response::redirect('/');
        }

        $content = $this->app->templates->render('users', 'login', [
            'error' => 'Invalid credentials.',
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request), 401);
    }

    public function logout(Request $request): Response
    {
        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $this->app->auth->logout();

        return Response::redirect('/');
    }
}

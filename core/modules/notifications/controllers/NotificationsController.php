<?php

declare(strict_types=1);

namespace Stratum\Modules\Notifications;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class NotificationsController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->app->auth->user();
        $service = new NotificationService($this->app->db);

        $content = $this->app->templates->render('notifications', 'index', [
            'notifications' => $service->listForUser((int) $user['id']),
            'unreadCount' => $service->unreadCount((int) $user['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function markRead(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $user = $this->app->auth->user();
        (new NotificationService($this->app->db))->markRead(
            (int) $request->param('id', '0'),
            (int) $user['id']
        );

        return Response::redirect('/notifications');
    }

    public function markAllRead(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $user = $this->app->auth->user();
        (new NotificationService($this->app->db))->markAllRead((int) $user['id']);

        return Response::redirect('/notifications');
    }
}

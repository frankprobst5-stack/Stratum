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

    /** Tiny JSON poll target for the live header badge — deliberately just a count, not the list, to keep polling cheap. */
    public function unreadCount(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::json(['unreadCount' => 0]);
        }

        $user = $this->app->auth->user();
        $count = (new NotificationService($this->app->db))->unreadCount((int) $user['id']);

        return Response::json(['unreadCount' => $count]);
    }

    /** The dropdown's fragment — fetched fresh each time it's opened, same template as the initial server-render. */
    public function panel(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::html('', 401);
        }

        $user = $this->app->auth->user();
        $service = new NotificationService($this->app->db);

        $html = $this->app->templates->render('notifications', 'panel', [
            'notifications' => array_slice($service->listForUser((int) $user['id']), 0, 8),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($html);
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

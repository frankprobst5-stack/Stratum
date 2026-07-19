<?php

declare(strict_types=1);

namespace Stratum\Modules\Ticker;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class TickerAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('ticker.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('ticker', 'admin-index', [
            'messages' => (new TickerService($this->app->db))->listAll(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('ticker.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $message = trim((string) $request->input('message', ''));
        if ($message !== '') {
            $user = $this->app->auth->user();

            (new TickerService($this->app->db))->createMessage(
                $message,
                $this->nullableInput($request, 'url'),
                $this->levelInput($request),
                $this->nullableInput($request, 'starts_at'),
                $this->nullableInput($request, 'ends_at'),
                (int) $request->input('weight', '0'),
                $user !== null ? (int) $user['id'] : null
            );
        }

        return Response::redirect('/admin/ticker');
    }

    public function update(Request $request): Response
    {
        if (($guard = $this->guard('ticker.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        $message = trim((string) $request->input('message', ''));

        if ($id > 0 && $message !== '') {
            (new TickerService($this->app->db))->updateMessage(
                $id,
                $message,
                $this->nullableInput($request, 'url'),
                $this->levelInput($request),
                $this->nullableInput($request, 'starts_at'),
                $this->nullableInput($request, 'ends_at'),
                (int) $request->input('weight', '0')
            );
        }

        return Response::redirect('/admin/ticker');
    }

    public function toggle(Request $request): Response
    {
        if (($guard = $this->guard('ticker.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        if ($id > 0) {
            (new TickerService($this->app->db))->toggleEnabled($id);
        }

        return Response::redirect('/admin/ticker');
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('ticker.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        if ($id > 0) {
            (new TickerService($this->app->db))->deleteMessage($id);
        }

        return Response::redirect('/admin/ticker');
    }

    private function nullableInput(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));

        return $value !== '' ? $value : null;
    }

    private function levelInput(Request $request): string
    {
        $level = (string) $request->input('level', 'info');

        return in_array($level, ['info', 'warning', 'urgent'], true) ? $level : 'info';
    }
}

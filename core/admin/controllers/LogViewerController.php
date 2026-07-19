<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\LogService;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class LogViewerController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('admin.access')) !== null) {
            return $guard;
        }

        $service = new LogService($this->app->db);
        $level = $request->query('level');
        $level = in_array($level, ['error', 'info'], true) ? $level : null;
        $page = max(1, (int) $request->query('page', '1'));

        $entries = $service->list($level, $page);
        $total = $service->count($level);

        $content = $this->app->templates->render('admin', 'log-viewer', [
            'entries' => $entries,
            'level' => $level,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($total / $service->perPage())),
            'total' => $total,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function clear(Request $request): Response
    {
        if (($guard = $this->guard('admin.access')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new LogService($this->app->db))->clearAll();

        return Response::redirect('/admin/system/logs');
    }
}

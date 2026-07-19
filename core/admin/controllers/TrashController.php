<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\TrashService;

final class TrashController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('trash.manage')) !== null) {
            return $guard;
        }

        $service = new TrashService($this->app->db, $this->app->modules);

        $content = $this->app->templates->render('admin', 'trash', [
            'items' => $service->listTrashed(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function restore(Request $request): Response
    {
        if (($guard = $this->guard('trash.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $type = (string) $request->input('type', '');
        $id = (int) $request->input('id', '0');

        (new TrashService($this->app->db, $this->app->modules))->restore($type, $id);

        return Response::redirect('/admin/trash');
    }
}

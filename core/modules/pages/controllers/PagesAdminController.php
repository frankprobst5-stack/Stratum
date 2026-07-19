<?php

declare(strict_types=1);

namespace Stratum\Modules\Pages;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class PagesAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('pages.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('pages', 'admin-index', [
            'pages' => (new PageService($this->app->db))->listAll(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->guard('pages.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('pages', 'admin-form', [
            'page' => null,
            'csrfToken' => $this->app->session->csrfToken(),
            'formAction' => '/admin/pages/create',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('pages.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new PageService($this->app->db))->create([
            'title' => (string) $request->input('title', ''),
            'body' => (string) $request->input('body', ''),
            'is_published' => $request->input('is_published') === '1',
        ]);

        return Response::redirect('/admin/pages');
    }

    public function showEdit(Request $request): Response
    {
        if (($guard = $this->guard('pages.manage')) !== null) {
            return $guard;
        }

        $page = (new PageService($this->app->db))->find((int) $request->param('id', '0'));
        if ($page === null) {
            return Response::notFound();
        }

        $content = $this->app->templates->render('pages', 'admin-form', [
            'page' => $page,
            'csrfToken' => $this->app->session->csrfToken(),
            'formAction' => '/admin/pages/' . $page['id'] . '/edit',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function update(Request $request): Response
    {
        if (($guard = $this->guard('pages.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new PageService($this->app->db))->update((int) $request->param('id', '0'), [
            'title' => (string) $request->input('title', ''),
            'body' => (string) $request->input('body', ''),
            'is_published' => $request->input('is_published') === '1',
        ]);

        return Response::redirect('/admin/pages');
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('pages.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new PageService($this->app->db))->softDelete((int) $request->param('id', '0'));

        return Response::redirect('/admin/pages');
    }
}

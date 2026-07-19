<?php

declare(strict_types=1);

namespace Stratum\Modules\Wiki;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class WikiAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('wiki.manage')) !== null) {
            return $guard;
        }

        $wiki = new WikiService($this->app->db);

        $content = $this->app->templates->render('wiki', 'admin-index', [
            'categories' => $wiki->listCategories(),
            'pages' => $wiki->listPages(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createCategory(Request $request): Response
    {
        if (($guard = $this->guard('wiki.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new WikiService($this->app->db))->createCategory($name);
        }

        return Response::redirect('/admin/wiki');
    }

    public function deletePage(Request $request): Response
    {
        if (($guard = $this->guard('wiki.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new WikiService($this->app->db))->softDeletePage((int) $request->param('id', '0'));

        return Response::redirect('/admin/wiki');
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Modules\Classifieds;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class ClassifiedsAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('classifieds.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('classifieds', 'admin-index', [
            'categories' => (new ClassifiedsService($this->app->db, $this->storageDir()))->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createCategory(Request $request): Response
    {
        if (($guard = $this->guard('classifieds.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new ClassifiedsService($this->app->db, $this->storageDir()))->createCategory($name);
        }

        return Response::redirect('/admin/classifieds');
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/classifieds';
    }
}

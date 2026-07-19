<?php

declare(strict_types=1);

namespace Stratum\Modules\Downloads;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class DownloadsAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('downloads.manage')) !== null) {
            return $guard;
        }

        $service = new DownloadService($this->app->db, $this->storageDir());
        $categories = array_map(
            fn (array $c): array => $c + ['files' => $service->listFiles((int) $c['id'])],
            $service->listCategories()
        );

        $content = $this->app->templates->render('downloads', 'admin-index', [
            'categories' => $categories,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createCategory(Request $request): Response
    {
        if (($guard = $this->guard('downloads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new DownloadService($this->app->db, $this->storageDir()))->createCategory($name);
        }

        return Response::redirect('/admin/downloads');
    }

    public function deleteFile(Request $request): Response
    {
        if (($guard = $this->guard('downloads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new DownloadService($this->app->db, $this->storageDir()))->softDeleteFile((int) $request->param('id', '0'));

        return Response::redirect('/admin/downloads');
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/downloads';
    }
}

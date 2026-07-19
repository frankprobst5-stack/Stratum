<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\BackupService;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class BackupController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('system.backup')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'backups', [
            'backups' => $this->service()->list(),
            'csrfToken' => $this->app->session->csrfToken(),
            'created' => $request->query('created') === '1',
            'error' => $request->query('error'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('system.backup')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        try {
            $this->service()->create();

            return Response::redirect('/admin/system/backups?created=1');
        } catch (\Throwable $e) {
            return Response::redirect('/admin/system/backups?error=' . rawurlencode($e->getMessage()));
        }
    }

    public function download(Request $request): Response
    {
        if (($guard = $this->guard('system.backup')) !== null) {
            return $guard;
        }

        $filename = (string) $request->param('filename', '');
        $path = $this->service()->path($filename);
        if ($path === null) {
            return Response::notFound();
        }

        $contentType = str_ends_with($filename, '.gz') ? 'application/gzip' : 'application/sql';

        return Response::file((string) file_get_contents($path), $contentType, $filename);
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('system.backup')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $this->service()->delete((string) $request->param('filename', ''));

        return Response::redirect('/admin/system/backups');
    }

    private function service(): BackupService
    {
        return new BackupService($this->app->db, $this->app->rootDir . '/storage/backups');
    }
}

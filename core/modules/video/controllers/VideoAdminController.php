<?php

declare(strict_types=1);

namespace Stratum\Modules\Video;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class VideoAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('video.manage')) !== null) {
            return $guard;
        }

        $service = new VideoService($this->app->db, $this->storageDir());
        $categories = array_map(
            fn (array $c): array => $c + ['videos' => $service->listVideos((int) $c['id'])],
            $service->listCategories()
        );

        $content = $this->app->templates->render('video', 'admin-index', [
            'categories' => $categories,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createCategory(Request $request): Response
    {
        if (($guard = $this->guard('video.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new VideoService($this->app->db, $this->storageDir()))->createCategory($name);
        }

        return Response::redirect('/admin/video');
    }

    public function deleteVideo(Request $request): Response
    {
        if (($guard = $this->guard('video.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new VideoService($this->app->db, $this->storageDir()))->softDeleteVideo((int) $request->param('id', '0'));

        return Response::redirect('/admin/video');
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/video';
    }
}

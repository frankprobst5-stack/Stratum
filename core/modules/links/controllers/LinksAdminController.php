<?php

declare(strict_types=1);

namespace Stratum\Modules\Links;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class LinksAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('links.manage')) !== null) {
            return $guard;
        }

        $service = new LinkService($this->app->db);
        $authors = new AuthService($this->app->db);
        $categories = $service->listCategories();
        $categoryNames = array_column($categories, 'name', 'id');

        $links = array_map(function (array $link) use ($authors, $categoryNames): array {
            $submitterId = $link['submitted_by'] !== null ? (int) $link['submitted_by'] : null;
            $submitter = $submitterId !== null ? $authors->findById($submitterId) : null;

            return $link + [
                'categoryName' => $categoryNames[$link['category_id']] ?? 'Unknown',
                'submitterName' => $submitter['username'] ?? 'Unknown',
            ];
        }, $service->listLinks());

        $content = $this->app->templates->render('links', 'admin-index', [
            'categories' => $categories,
            'links' => $links,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createCategory(Request $request): Response
    {
        if (($guard = $this->guard('links.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new LinkService($this->app->db))->createCategory($name);
        }

        return Response::redirect('/admin/links');
    }

    public function deleteLink(Request $request): Response
    {
        if (($guard = $this->guard('links.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new LinkService($this->app->db))->softDeleteLink((int) $request->param('id', '0'));

        return Response::redirect('/admin/links');
    }
}

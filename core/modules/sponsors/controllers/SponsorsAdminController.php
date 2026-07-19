<?php

declare(strict_types=1);

namespace Stratum\Modules\Sponsors;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class SponsorsAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('sponsors.manage')) !== null) {
            return $guard;
        }

        $service = new SponsorService($this->app->db);

        $content = $this->app->templates->render('sponsors', 'admin-index', [
            'sponsors' => $service->listAll(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('sponsors.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        $logoUrl = trim((string) $request->input('logo_url', ''));
        $linkUrl = trim((string) $request->input('link_url', ''));

        if ($name !== '' && $this->isValidUrl($logoUrl) && $this->isValidUrl($linkUrl)) {
            (new SponsorService($this->app->db))->create(
                $name,
                $logoUrl,
                $linkUrl,
                (int) $request->input('weight', '0')
            );
        }

        return Response::redirect('/admin/sponsors');
    }

    public function toggle(Request $request): Response
    {
        if (($guard = $this->guard('sponsors.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new SponsorService($this->app->db);
        $sponsor = $service->find((int) $request->param('id', '0'));
        if ($sponsor !== null) {
            $service->setActive((int) $sponsor['id'], !$sponsor['is_active']);
        }

        return Response::redirect('/admin/sponsors');
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('sponsors.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new SponsorService($this->app->db))->softDelete((int) $request->param('id', '0'));

        return Response::redirect('/admin/sponsors');
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Modules\Affiliates;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class AffiliatesAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('affiliates.manage')) !== null) {
            return $guard;
        }

        $service = new AffiliateService($this->app->db);

        $content = $this->app->templates->render('affiliates', 'admin-index', [
            'links' => $service->listAll(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('affiliates.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $label = trim((string) $request->input('label', ''));
        $url = trim((string) $request->input('url', ''));

        if ($label !== '' && $this->isValidUrl($url)) {
            (new AffiliateService($this->app->db))->create(
                $label,
                $url,
                trim((string) $request->input('description', '')),
                (int) $request->input('weight', '0')
            );
        }

        return Response::redirect('/admin/affiliates');
    }

    public function toggle(Request $request): Response
    {
        if (($guard = $this->guard('affiliates.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new AffiliateService($this->app->db);
        $link = $service->find((int) $request->param('id', '0'));
        if ($link !== null) {
            $service->setActive((int) $link['id'], !$link['is_active']);
        }

        return Response::redirect('/admin/affiliates');
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('affiliates.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new AffiliateService($this->app->db))->softDelete((int) $request->param('id', '0'));

        return Response::redirect('/admin/affiliates');
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }
}

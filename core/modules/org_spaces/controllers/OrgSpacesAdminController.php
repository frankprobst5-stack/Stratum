<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class OrgSpacesAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('org_spaces.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('org_spaces', 'admin-index', [
            'orgs' => (new OrgSpaceService($this->app->db, $this->app->permissions))->listOrgs(false),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('org_spaces.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new OrgSpaceService($this->app->db, $this->app->permissions))->createOrg($name, (string) $request->input('description', ''));
        }

        return Response::redirect('/admin/org_spaces');
    }

    public function toggleActive(Request $request): Response
    {
        if (($guard = $this->guard('org_spaces.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrg((int) $request->param('id', '0'));
        if ($org !== null) {
            $service->setOrgActive((int) $org['id'], !$org['is_active']);
        }

        return Response::redirect('/admin/org_spaces');
    }
}

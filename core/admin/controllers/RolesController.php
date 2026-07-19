<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class RolesController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('roles.manage')) !== null) {
            return $guard;
        }

        $grants = $this->app->permissions->listGrants();
        $grantSet = [];
        foreach ($grants as $grant) {
            $grantSet["{$grant['role_id']}:{$grant['capability_id']}"] = true;
        }

        $content = $this->app->templates->render('admin', 'roles', [
            'roles' => $this->app->permissions->listRoles(),
            'capabilities' => $this->app->permissions->listCapabilities(),
            'grantSet' => $grantSet,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function update(Request $request): Response
    {
        if (($guard = $this->guard('roles.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $grantsInput = $request->inputArray('grants');

        foreach ($this->app->permissions->listRoles() as $role) {
            foreach ($this->app->permissions->listCapabilities() as $capability) {
                $checked = isset($grantsInput[$role['id']][$capability['id']]);

                if ($checked) {
                    $this->app->permissions->grant($role['id'], $capability['id']);
                } else {
                    $this->app->permissions->revoke($role['id'], $capability['id']);
                }
            }
        }

        return Response::redirect('/admin/roles');
    }

    /**
     * Read-only "who can actually do what" report — distinct from the
     * editable role x capability matrix above, which never shows which
     * real members hold a role, and never shows auto-provisioned scoped
     * roles at all (deliberately excluded from that matrix since Stage
     * 2 — see migration 003's own docblock). Both gaps are exactly what
     * an audit view exists to close.
     */
    public function audit(Request $request): Response
    {
        if (($guard = $this->guard('roles.manage')) !== null) {
            return $guard;
        }

        $authors = new AuthService($this->app->db);
        $resolveUsernames = fn (array $userIds): array => array_map(
            fn (int $id): string => $authors->findById($id)['username'] ?? 'Unknown',
            $userIds
        );

        $siteWideRoleList = $this->app->permissions->listRoles(siteWideOnly: true);
        $siteWideIds = array_column($siteWideRoleList, 'id');

        $siteWideRoles = array_map(
            fn (array $role): array => $role + ['members' => $resolveUsernames($this->app->permissions->usersInRole($role['id']))],
            $siteWideRoleList
        );

        $scopedRoles = array_map(
            fn (array $role): array => $role + ['members' => $resolveUsernames($this->app->permissions->usersInRole((int) $role['id']))],
            array_filter(
                $this->app->permissions->listRoles(siteWideOnly: false),
                static fn (array $role): bool => !in_array($role['id'], $siteWideIds, true)
            )
        );

        $content = $this->app->templates->render('admin', 'roles-audit', [
            'siteWideRoles' => $siteWideRoles,
            'scopedRoles' => $scopedRoles,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createRole(Request $request): Response
    {
        if (($guard = $this->guard('roles.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            $this->app->permissions->createRole($name);
        }

        return Response::redirect('/admin/roles');
    }
}

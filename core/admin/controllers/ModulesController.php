<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\AddonPackageInstaller;
use Stratum\Core\PackageInstallException;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\StarterPackageBuilder;

final class ModulesController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('modules.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'modules', [
            'modules' => $this->app->modules->list(),
            'csrfToken' => $this->app->session->csrfToken(),
            'uploadError' => $request->query('error'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function dependencies(Request $request): Response
    {
        if (($guard = $this->guard('modules.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'module-dependencies', [
            'graph' => $this->app->modules->dependencyGraph(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function toggle(Request $request): Response
    {
        if (($guard = $this->guard('modules.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (string) $request->param('id', '');
        $enabled = $request->input('enabled') === '1';

        try {
            $this->app->modules->setEnabled($id, $enabled);
        } catch (\RuntimeException) {
            // Non-disableable module, unmet dependency, or a dependent module still needs
            // it enabled — ignore and fall through to the redirect. See ModuleManager.
        }

        return Response::redirect('/admin/modules');
    }

    /**
     * Addon upload reuses `modules.manage` rather than a new capability —
     * an addon IS a module the moment it's installed, the same concern
     * this whole screen already gates.
     */
    public function uploadAddon(Request $request): Response
    {
        if (($guard = $this->guard('modules.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $file = $request->file('package');
        if ($file === null) {
            return Response::redirect('/admin/modules?error=' . rawurlencode('No file was uploaded.'));
        }

        $installer = new AddonPackageInstaller($this->app->db, $this->app->rootDir . '/core/modules', $this->app->rootDir . '/storage/addons');

        try {
            $installer->install($file['tmp_name']);
        } catch (PackageInstallException $e) {
            return Response::redirect('/admin/modules?error=' . rawurlencode($e->getMessage()));
        }

        return Response::redirect('/admin/modules');
    }

    /** Refuses to touch anything but a custom (uploaded) module — see ModuleManager::isCustom(). */
    public function deleteAddon(Request $request): Response
    {
        if (($guard = $this->guard('modules.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (string) $request->param('id', '');
        if (!$this->app->modules->isCustom($id)) {
            return Response::redirect('/admin/modules');
        }

        try {
            $this->app->modules->setEnabled($id, false);
        } catch (\RuntimeException) {
            // Another module still depends on it — refuse the whole delete, same as
            // disabling would refuse on its own; nothing has been removed yet.
            return Response::redirect('/admin/modules?error=' . rawurlencode("Cannot remove '{$id}' — another enabled module still depends on it."));
        }

        $this->app->modules->forgetCustomModule($id);
        (new AddonPackageInstaller($this->app->db, $this->app->rootDir . '/core/modules', $this->app->rootDir . '/storage/addons'))->remove($id);

        return Response::redirect('/admin/modules');
    }

    public function downloadAddonStarter(Request $request): Response
    {
        if (($guard = $this->guard('modules.manage')) !== null) {
            return $guard;
        }

        $zip = (new StarterPackageBuilder())->build($this->app->rootDir . '/core/starters/addon');

        return Response::file($zip, 'application/zip', 'stratum-addon-starter.zip');
    }
}

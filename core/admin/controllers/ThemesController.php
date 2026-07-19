<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\PackageInstallException;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\StarterPackageBuilder;
use Stratum\Core\ThemeManager;
use Stratum\Core\ThemePackageInstaller;

final class ThemesController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('themes.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'themes', [
            'themes' => $this->themeManager()->list(),
            'csrfToken' => $this->app->session->csrfToken(),
            'uploadError' => $request->query('error'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function upload(Request $request): Response
    {
        if (($guard = $this->guard('themes.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $file = $request->file('package');
        if ($file === null) {
            return Response::redirect('/admin/themes?error=' . rawurlencode('No file was uploaded.'));
        }

        $installer = new ThemePackageInstaller($this->app->rootDir . '/themes', $this->app->rootDir . '/storage/themes');

        try {
            $installer->install($file['tmp_name']);
        } catch (PackageInstallException $e) {
            return Response::redirect('/admin/themes?error=' . rawurlencode($e->getMessage()));
        }

        return Response::redirect('/admin/themes');
    }

    /**
     * Scaffolds a lean child theme (theme.json + empty overrides/, no
     * templates/layout.php) directly on disk rather than requiring a
     * hand-built zip — the "Do both together, full WordPress-style" block
     * management upgrade set the precedent that a non-technical club
     * admin shouldn't need to author files by hand for a common action.
     * Further customization (real override templates) still happens by
     * hand in storage/themes/{id}/overrides/ or by uploading a proper
     * theme package later — this just removes the zero-config barrier to
     * getting a genuinely independent, activatable child theme to start
     * from.
     */
    public function createChild(Request $request): Response
    {
        if (($guard = $this->guard('themes.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = trim((string) $request->input('id', ''));
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $parentId = trim((string) $request->input('parent_id', ''));

        // Only a built-in theme may be a parent — the same constraint
        // TemplateEngine::resolve()'s own parent-override lookup already
        // enforces (it only ever checks $themesDir, never the custom
        // themes directory), so a parent chosen here is guaranteed to
        // actually resolve at render time.
        $builtIn = array_filter($this->themeManager()->list(), static fn (array $t): bool => !$t['custom']);
        $validParent = array_filter($builtIn, static fn (array $t): bool => $t['id'] === $parentId) !== [];

        if (!$validParent) {
            return Response::redirect('/admin/themes?error=' . rawurlencode('Choose a valid built-in theme to base the child theme on.'));
        }

        try {
            (new ThemePackageInstaller($this->app->rootDir . '/themes', $this->app->rootDir . '/storage/themes'))
                ->createChild($id, $name, $description, $parentId);
        } catch (PackageInstallException $e) {
            return Response::redirect('/admin/themes?error=' . rawurlencode($e->getMessage()));
        }

        return Response::redirect('/admin/themes');
    }

    public function activate(Request $request): Response
    {
        if (($guard = $this->guard('themes.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $this->themeManager()->setActive((string) $request->param('id', ''));

        return Response::redirect('/admin/themes');
    }

    /** Refuses to remove the currently-active theme (would leave the site with no layout to render) or a built-in one. */
    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('themes.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $manager = $this->themeManager();
        $id = (string) $request->param('id', '');

        if (!$manager->isCustom($id)) {
            return Response::redirect('/admin/themes');
        }

        if ($manager->activeThemeId() === $id) {
            return Response::redirect('/admin/themes?error=' . rawurlencode('Cannot remove the active theme — activate a different one first.'));
        }

        (new ThemePackageInstaller($this->app->rootDir . '/themes', $this->app->rootDir . '/storage/themes'))->remove($id);

        return Response::redirect('/admin/themes');
    }

    public function downloadStarter(Request $request): Response
    {
        if (($guard = $this->guard('themes.manage')) !== null) {
            return $guard;
        }

        $zip = (new StarterPackageBuilder())->build($this->app->rootDir . '/core/starters/theme');

        return Response::file($zip, 'application/zip', 'stratum-theme-starter.zip');
    }

    private function themeManager(): ThemeManager
    {
        return new ThemeManager($this->app->db, $this->app->rootDir . '/themes', $this->app->rootDir . '/storage/themes');
    }
}

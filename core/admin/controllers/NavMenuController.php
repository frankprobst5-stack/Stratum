<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\NavMenuService;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class NavMenuController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('nav.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'menu', [
            'items' => (new NavMenuService($this->app->db))->adminList($this->liveModuleNavItems()),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('nav.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new NavMenuService($this->app->db))->createCustom(
            (string) $request->input('label', ''),
            (string) $request->input('route', '')
        );

        return Response::redirect('/admin/menu');
    }

    public function update(Request $request): Response
    {
        if (($guard = $this->guard('nav.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new NavMenuService($this->app->db))->updateItem(
            (int) $request->param('id', '0'),
            (string) $request->input('label', ''),
            (string) $request->input('placement', 'more')
        );

        return Response::redirect('/admin/menu');
    }

    public function moveUp(Request $request): Response
    {
        if (($guard = $this->guard('nav.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new NavMenuService($this->app->db))->moveUp((int) $request->param('id', '0'));

        return Response::redirect('/admin/menu');
    }

    public function moveDown(Request $request): Response
    {
        if (($guard = $this->guard('nav.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new NavMenuService($this->app->db))->moveDown((int) $request->param('id', '0'));

        return Response::redirect('/admin/menu');
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('nav.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new NavMenuService($this->app->db))->delete((int) $request->param('id', '0'));

        return Response::redirect('/admin/menu');
    }

    /**
     * /search is excluded here too — same reasoning as
     * App::renderPage()'s identical filter: it already has its own icon
     * in topbar_actions, so the menu builder never even offers it as a
     * manageable row.
     *
     * @return array<int, array{label: string, route: string}>
     */
    private function liveModuleNavItems(): array
    {
        return array_values(array_filter(
            $this->app->modules->navItems(),
            static fn (array $item): bool => $item['route'] !== '/search'
        ));
    }
}

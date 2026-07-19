<?php

declare(strict_types=1);

namespace Stratum\Modules\RssAggregator;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class RssAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('rss.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('rss_aggregator', 'admin-index', [
            'sources' => (new RssSourceService($this->app->db))->listSources(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('rss.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        $feedUrl = trim((string) $request->input('feed_url', ''));

        if ($name !== '' && $feedUrl !== '') {
            (new RssSourceService($this->app->db))->createSource($name, $feedUrl);
        }

        return Response::redirect('/admin/rss');
    }

    public function toggle(Request $request): Response
    {
        if (($guard = $this->guard('rss.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        if ($id > 0) {
            (new RssSourceService($this->app->db))->toggleEnabled($id);
        }

        return Response::redirect('/admin/rss');
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('rss.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        if ($id > 0) {
            (new RssSourceService($this->app->db))->deleteSource($id);
        }

        return Response::redirect('/admin/rss');
    }

    public function toggleAutoPublish(Request $request): Response
    {
        if (($guard = $this->guard('rss.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        $sources = new RssSourceService($this->app->db);
        $source = $sources->find($id);

        if ($source !== null) {
            $admin = $this->app->auth->user();
            $sources->setAutoPublish($id, !((bool) $source['auto_publish']), (int) $admin['id']);
        }

        return Response::redirect('/admin/rss');
    }

    public function refresh(Request $request): Response
    {
        if (($guard = $this->guard('rss.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        if ($id > 0) {
            (new RssFetcher($this->app->db))->fetchAndStore($id);
        }

        return Response::redirect('/admin/rss');
    }
}

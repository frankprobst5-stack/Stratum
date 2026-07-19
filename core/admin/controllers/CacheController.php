<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\PageCache;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class CacheController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('admin.access')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'cache', [
            'enabled' => $this->app->config->getBool('PAGE_CACHE_ENABLED', false),
            'ttlSeconds' => $this->app->config->getInt('PAGE_CACHE_TTL_SECONDS', 300),
            'stats' => $this->service()->stats(),
            'csrfToken' => $this->app->session->csrfToken(),
            'cleared' => $request->query('cleared') === '1',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function clear(Request $request): Response
    {
        if (($guard = $this->guard('admin.access')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $this->service()->clear();

        return Response::redirect('/admin/system/cache?cleared=1');
    }

    private function service(): PageCache
    {
        return new PageCache(
            $this->app->rootDir . '/storage/cache/pages',
            $this->app->config->getInt('PAGE_CACHE_TTL_SECONDS', 300)
        );
    }
}

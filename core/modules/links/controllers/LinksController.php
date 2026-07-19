<?php

declare(strict_types=1);

namespace Stratum\Modules\Links;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class LinksController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new LinkService($this->app->db);

        $categories = array_map(
            fn (array $c): array => $c + ['links' => $service->listLinks((int) $c['id'])],
            $service->listCategories()
        );

        $content = $this->app->templates->render('links', 'index', [
            'categories' => $categories,
            'canSubmit' => $this->app->auth->can('links.create'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->requireCapability('links.create')) !== null) {
            return $guard;
        }

        $service = new LinkService($this->app->db);

        $content = $this->app->templates->render('links', 'form', [
            'categories' => $service->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->requireCapability('links.create')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $categoryId = (int) $request->input('category_id', '0');
        $title = trim((string) $request->input('title', ''));
        $url = trim((string) $request->input('url', ''));
        $description = (string) $request->input('description', '');

        if ($categoryId <= 0 || $title === '' || !$this->isValidUrl($url)) {
            return Response::redirect('/links/submit');
        }

        $user = $this->app->auth->user();
        $service = new LinkService($this->app->db);
        $linkId = $service->createLink($categoryId, (int) $user['id'], $title, $url, $description);

        return Response::redirect('/links?added=' . $linkId);
    }

    /** Tracks a click, then redirects to the external URL — same "count then redirect" shape downloads' download action uses for download_count. */
    public function visit(Request $request): Response
    {
        $service = new LinkService($this->app->db);
        $link = $service->findLink((int) $request->param('id', '0'));
        if ($link === null) {
            return Response::notFound();
        }

        $service->incrementClickCount((int) $link['id']);

        return Response::redirect($link['url']);
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }

    private function requireCapability(string $capability): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can($capability)) {
            return Response::forbidden();
        }

        return null;
    }
}

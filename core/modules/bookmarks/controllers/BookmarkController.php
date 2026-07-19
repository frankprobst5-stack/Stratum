<?php

declare(strict_types=1);

namespace Stratum\Modules\Bookmarks;

use Stratum\Core\App;
use Stratum\Core\ContentResolver;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class BookmarkController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $service = new BookmarkService($this->app->db);
        $user = $this->app->auth->user();

        $content = $this->app->templates->render('bookmarks', 'index', [
            'bookmarks' => $service->listForUser((int) $user['id']),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function toggle(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $type = (string) $request->input('bookmarkable_type', '');
        $id = (int) $request->input('bookmarkable_id', '0');
        $redirectTo = $this->safeRedirectTarget($request->input('redirect_to', '/'));

        $service = new BookmarkService($this->app->db);
        if (!$service->isBookmarkable($type)) {
            return Response::notFound();
        }

        // Confirms the target still resolves (exists, published, not
        // deleted) before writing a row — same guard moderation's create()
        // applies before accepting a report.
        if ((new ContentResolver($this->app->db))->resolve($type, $id) === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $service->toggle($type, $id, (int) $user['id']);

        return Response::redirect($redirectTo);
    }

    /** Only ever redirect to a local path — never trust the client-supplied target as-is (same guard CommentsController uses). */
    private function safeRedirectTarget(?string $path): string
    {
        if ($path === null || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        return $path;
    }
}

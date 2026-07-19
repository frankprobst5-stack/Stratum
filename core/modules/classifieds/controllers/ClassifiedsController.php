<?php

declare(strict_types=1);

namespace Stratum\Modules\Classifieds;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\SeoService;
use Stratum\Modules\Users\AuthService;

final class ClassifiedsController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new ClassifiedsService($this->app->db, $this->storageDir());

        $categories = array_map(
            fn (array $c): array => $c + ['listings' => $service->listListings((int) $c['id'])],
            $service->listCategories()
        );

        $content = $this->app->templates->render('classifieds', 'index', [
            'categories' => $categories,
            'canPost' => $this->app->auth->can('classifieds.post'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->requireCapability('classifieds.post')) !== null) {
            return $guard;
        }

        $service = new ClassifiedsService($this->app->db, $this->storageDir());

        $content = $this->app->templates->render('classifieds', 'form', [
            'categories' => $service->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->requireCapability('classifieds.post')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $categoryId = (int) $request->input('category_id', '0');
        $title = trim((string) $request->input('title', ''));
        if ($categoryId <= 0 || $title === '') {
            return Response::redirect('/classifieds/create');
        }

        $service = new ClassifiedsService($this->app->db, $this->storageDir());

        $validatedPhoto = null;
        $file = $request->file('photo');
        if ($file !== null) {
            $validatedPhoto = $service->validateUpload($file);
            if ($validatedPhoto === null) {
                return Response::html('Invalid photo — check the file type and size (max 10MB).', 422);
            }
        }

        $price = trim((string) $request->input('price', ''));
        $user = $this->app->auth->user();

        $listingId = $service->createListing(
            $categoryId,
            (int) $user['id'],
            $title,
            (string) $request->input('description', ''),
            $price !== '' ? $price : null,
            $validatedPhoto
        );

        return Response::redirect('/classifieds/listings/' . $listingId);
    }

    public function listing(Request $request): Response
    {
        $service = new ClassifiedsService($this->app->db, $this->storageDir());
        $listing = $service->findListing((int) $request->param('id', '0'));
        if ($listing === null) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $sellerName = $this->sellerName($authors, $listing['user_id'] !== null ? (int) $listing['user_id'] : null);

        $currentUser = $this->app->auth->user();
        $isOwner = $currentUser !== null && (int) $currentUser['id'] === (int) ($listing['user_id'] ?? 0);
        $canManage = $isOwner || $this->app->auth->can('classifieds.manage');

        $content = $this->app->templates->render('classifieds', 'listing', [
            'listing' => $listing,
            'sellerName' => $sellerName,
            'canManage' => $canManage,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $listing['title'],
            'description' => (new SeoService())->excerpt((string) ($listing['description'] ?? '')),
            'ogImage' => $listing['thumbnail_filename'] !== null
                ? '/classifieds/listings/' . $listing['id'] . '/thumbnail'
                : null,
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function markSold(Request $request): Response
    {
        return $this->mutateOwnListing($request, static function (ClassifiedsService $service, array $listing): void {
            $service->setStatus((int) $listing['id'], 'sold');
        });
    }

    public function delete(Request $request): Response
    {
        return $this->mutateOwnListing($request, static function (ClassifiedsService $service, array $listing): void {
            $service->softDeleteListing((int) $listing['id']);
        }, redirectTo: '/classifieds');
    }

    public function image(Request $request): Response
    {
        return $this->serveImage($request, useThumbnail: false);
    }

    public function thumbnail(Request $request): Response
    {
        return $this->serveImage($request, useThumbnail: true);
    }

    /** @param callable(ClassifiedsService, array<string, mixed>): void $action */
    private function mutateOwnListing(Request $request, callable $action, string $redirectTo = ''): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $service = new ClassifiedsService($this->app->db, $this->storageDir());
        $listing = $service->findListing((int) $request->param('id', '0'));
        if ($listing === null) {
            return Response::notFound();
        }

        $currentUser = $this->app->auth->user();
        $isOwner = (int) $currentUser['id'] === (int) ($listing['user_id'] ?? 0);
        if (!$isOwner && !$this->app->auth->can('classifieds.manage')) {
            return Response::forbidden();
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $action($service, $listing);

        return Response::redirect($redirectTo !== '' ? $redirectTo : '/classifieds/listings/' . $listing['id']);
    }

    private function serveImage(Request $request, bool $useThumbnail): Response
    {
        $service = new ClassifiedsService($this->app->db, $this->storageDir());
        $listing = $service->findListing((int) $request->param('id', '0'));
        if ($listing === null || $listing['filename'] === null) {
            return Response::notFound();
        }

        $path = $useThumbnail ? $service->absoluteThumbnailPath($listing) : $service->absolutePath($listing);
        if (!is_file($path)) {
            return Response::notFound();
        }

        $contentType = $useThumbnail ? 'image/jpeg' : $listing['mime_type'];

        return Response::streamFile((string) file_get_contents($path), $contentType);
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

    private function sellerName(AuthService $authors, ?int $userId): string
    {
        if ($userId === null) {
            return 'Unknown';
        }

        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/classifieds';
    }
}

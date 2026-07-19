<?php

declare(strict_types=1);

namespace Stratum\Modules\Gallery;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\ReputationService;
use Stratum\Core\Response;
use Stratum\Core\SeoService;
use Stratum\Modules\Comments\CommentService;
use Stratum\Modules\Users\AuthService;

final class GalleryController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new GalleryService($this->app->db, $this->storageDir());

        $albums = array_map(function (array $album) use ($service): array {
            $photos = $service->listPhotos((int) $album['id']);

            return $album + ['photoCount' => count($photos), 'coverPhoto' => $photos[0] ?? null];
        }, $service->listAlbums());

        $content = $this->app->templates->render('gallery', 'index', [
            'albums' => $albums,
            'canUpload' => $this->app->auth->can('gallery.upload'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->requireCapability('gallery.upload')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('gallery', 'form', [
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->requireCapability('gallery.upload')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return Response::redirect('/gallery/create');
        }

        $service = new GalleryService($this->app->db, $this->storageDir());
        $validated = $this->validateBatch($service, $request->files('photos'));

        if ($validated === []) {
            // No valid photos in the batch — an album is never created empty,
            // same reasoning as ForumService::createTopicWithFirstPost().
            return Response::redirect('/gallery/create');
        }

        $user = $this->app->auth->user();
        $albumId = $service->createAlbum($title, (string) $request->input('description', ''), (int) $user['id'], $validated);
        (new ReputationService($this->app))->award((int) $user['id'], 1);

        return Response::redirect('/gallery/albums/' . $albumId);
    }

    public function album(Request $request): Response
    {
        $service = new GalleryService($this->app->db, $this->storageDir());
        $album = $service->findAlbum((int) $request->param('id', '0'));
        if ($album === null) {
            return Response::notFound();
        }

        $photos = $service->listPhotos((int) $album['id']);

        $content = $this->app->templates->render('gallery', 'album', [
            'album' => $album,
            'photos' => $photos,
            'canUpload' => $this->app->auth->can('gallery.upload'),
            'canManage' => $this->app->auth->can('gallery.manage'),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $album['title'],
            'description' => (new SeoService())->excerpt((string) ($album['description'] ?? '')),
            'ogImage' => isset($photos[0]) ? '/gallery/photos/' . $photos[0]['id'] . '/thumbnail' : null,
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function addPhotos(Request $request): Response
    {
        if (($guard = $this->requireCapability('gallery.upload')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new GalleryService($this->app->db, $this->storageDir());
        $albumId = (int) $request->param('id', '0');
        $album = $service->findAlbum($albumId);
        if ($album === null) {
            return Response::notFound();
        }

        $validated = $this->validateBatch($service, $request->files('photos'));
        $user = $this->app->auth->user();
        $service->addPhotos($albumId, (int) $user['id'], $validated);

        return Response::redirect('/gallery/albums/' . $albumId);
    }

    public function photo(Request $request): Response
    {
        $service = new GalleryService($this->app->db, $this->storageDir());
        $photo = $service->findPhoto((int) $request->param('id', '0'));
        if ($photo === null) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $comments = new CommentService($this->app->db);
        $commentRows = array_map(
            fn (array $c): array => $c + ['authorName' => $this->username($authors, (int) $c['user_id'])],
            $comments->listFor('gallery_photo', (int) $photo['id'])
        );

        $currentUser = $this->app->auth->user();
        $userId = $currentUser !== null ? (int) $currentUser['id'] : null;

        $content = $this->app->templates->render('gallery', 'photo', [
            'photo' => $photo,
            'exif' => $photo['exif_json'] !== null ? json_decode((string) $photo['exif_json'], true) : null,
            'comments' => $commentRows,
            'likeCount' => $service->likeCount((int) $photo['id']),
            'hasLiked' => $userId !== null && $service->hasLiked((int) $photo['id'], $userId),
            'canComment' => $this->app->auth->can('comments.create'),
            'canManage' => $this->app->auth->can('gallery.manage'),
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $photo['caption'] !== null && $photo['caption'] !== '' ? $photo['caption'] : 'Photo',
            'ogImage' => '/gallery/photos/' . $photo['id'] . '/thumbnail',
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function toggleLike(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $photoId = (int) $request->param('id', '0');
        $service = new GalleryService($this->app->db, $this->storageDir());
        if ($service->findPhoto($photoId) === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $service->toggleLike($photoId, (int) $user['id']);

        return Response::redirect('/gallery/photos/' . $photoId);
    }

    public function deletePhoto(Request $request): Response
    {
        if (($guard = $this->requireCapability('gallery.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new GalleryService($this->app->db, $this->storageDir());
        $photo = $service->findPhoto((int) $request->param('id', '0'));
        if ($photo === null) {
            return Response::notFound();
        }

        $service->softDeletePhoto((int) $photo['id']);

        return Response::redirect('/gallery/albums/' . $photo['album_id']);
    }

    public function deleteAlbum(Request $request): Response
    {
        if (($guard = $this->requireCapability('gallery.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new GalleryService($this->app->db, $this->storageDir()))->softDeleteAlbum((int) $request->param('id', '0'));

        return Response::redirect('/gallery');
    }

    public function image(Request $request): Response
    {
        return $this->serveImage($request, useThumbnail: false);
    }

    public function thumbnail(Request $request): Response
    {
        return $this->serveImage($request, useThumbnail: true);
    }

    private function serveImage(Request $request, bool $useThumbnail): Response
    {
        $service = new GalleryService($this->app->db, $this->storageDir());
        $photo = $service->findPhoto((int) $request->param('id', '0'));
        if ($photo === null) {
            return Response::notFound();
        }

        $path = $useThumbnail ? $service->absoluteThumbnailPath($photo) : $service->absolutePath($photo);
        if (!is_file($path)) {
            return Response::notFound();
        }

        $contentType = $useThumbnail ? 'image/jpeg' : $photo['mime_type'];

        return Response::streamFile((string) file_get_contents($path), $contentType);
    }

    /**
     * @param array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     * @return array<int, array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int}>
     */
    private function validateBatch(GalleryService $service, array $files): array
    {
        $validated = [];
        foreach ($files as $file) {
            $result = $service->validateUpload($file);
            if ($result !== null) {
                $validated[] = $result;
            }
        }

        return $validated;
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

    private function username(AuthService $authors, int $userId): string
    {
        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/gallery';
    }
}

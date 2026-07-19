<?php

declare(strict_types=1);

namespace Stratum\Modules\Video;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\SeoService;
use Stratum\Modules\Comments\CommentService;
use Stratum\Modules\Users\AuthService;

final class VideoController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new VideoService($this->app->db, $this->storageDir());

        $categories = array_map(
            fn (array $c): array => $c + ['videos' => $service->listVideos((int) $c['id'])],
            $service->listCategories()
        );

        $content = $this->app->templates->render('video', 'index', [
            'categories' => $categories,
            'canUpload' => $this->app->auth->can('video.upload'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function show(Request $request): Response
    {
        $service = new VideoService($this->app->db, $this->storageDir());
        $video = $service->findVideo((int) $request->param('id', '0'));
        if ($video === null) {
            return Response::notFound();
        }

        $service->incrementViewCount((int) $video['id']);

        $authors = new AuthService($this->app->db);
        $comments = new CommentService($this->app->db);
        $commentRows = array_map(
            fn (array $c): array => $c + ['authorName' => $this->username($authors, (int) $c['user_id'])],
            $comments->listFor('video', (int) $video['id'])
        );

        $content = $this->app->templates->render('video', 'show', [
            'video' => $video,
            'comments' => $commentRows,
            'canComment' => $this->app->auth->can('comments.create'),
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $video['title'],
            'description' => (new SeoService())->excerpt((string) ($video['description'] ?? '')),
            'ogType' => 'video.other',
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->requireCapability('video.upload')) !== null) {
            return $guard;
        }

        $service = new VideoService($this->app->db, $this->storageDir());

        $content = $this->app->templates->render('video', 'form', [
            'categories' => $service->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->requireCapability('video.upload')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $categoryId = (int) $request->input('category_id', '0');
        $title = trim((string) $request->input('title', ''));
        $description = (string) $request->input('description', '');
        $url = trim((string) $request->input('url', ''));
        $uploadedFile = $request->file('file');

        if ($categoryId <= 0 || $title === '') {
            return Response::redirect('/videos/create');
        }

        $service = new VideoService($this->app->db, $this->storageDir());
        $user = $this->app->auth->user();

        if ($url !== '') {
            $parsed = (new VideoUrlParser())->parse($url);
            if ($parsed === null) {
                return Response::html('That URL is not a recognized YouTube or Vimeo link.', 422);
            }

            $videoId = $service->createFromUrl($categoryId, (int) $user['id'], $title, $description, $parsed['sourceType'], $parsed['externalId']);

            return Response::redirect('/videos/' . $videoId);
        }

        if ($uploadedFile !== null) {
            $validated = $service->validateUpload($uploadedFile);
            if ($validated === null) {
                return Response::html('Invalid file — check the file type and size (max 50MB).', 422);
            }

            $videoId = $service->createFromUpload($categoryId, (int) $user['id'], $title, $description, $validated);

            return Response::redirect('/videos/' . $videoId);
        }

        return Response::redirect('/videos/create');
    }

    public function playlistIndex(Request $request): Response
    {
        $service = new VideoService($this->app->db, $this->storageDir());

        $content = $this->app->templates->render('video', 'playlist-index', [
            'playlists' => $service->listPlaylists(),
            'canManage' => $this->app->auth->can('video.manage'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function playlistShow(Request $request): Response
    {
        $service = new VideoService($this->app->db, $this->storageDir());
        $playlist = $service->findPlaylistBySlug((string) $request->param('slug', ''));
        if ($playlist === null) {
            return Response::notFound();
        }

        $canManage = $this->app->auth->can('video.manage');

        $content = $this->app->templates->render('video', 'playlist-show', [
            'playlist' => $playlist,
            'items' => $service->listPlaylistVideos((int) $playlist['id']),
            'allVideos' => $canManage ? $this->allVideosFlat($service) : [],
            'canManage' => $canManage,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showCreatePlaylist(Request $request): Response
    {
        if (($guard = $this->requireCapability('video.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('video', 'playlist-form', [
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createPlaylist(Request $request): Response
    {
        if (($guard = $this->requireCapability('video.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return Response::redirect('/videos/playlists/create');
        }

        $user = $this->app->auth->user();
        $service = new VideoService($this->app->db, $this->storageDir());
        $playlistId = $service->createPlaylist($title, (string) $request->input('description', ''), (int) $user['id']);
        $playlist = $service->findPlaylist($playlistId);

        return Response::redirect('/videos/playlists/' . $playlist['slug']);
    }

    public function deletePlaylist(Request $request): Response
    {
        if (($guard = $this->requireCapability('video.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new VideoService($this->app->db, $this->storageDir()))->softDeletePlaylist((int) $request->param('id', '0'));

        return Response::redirect('/videos/playlists');
    }

    public function addToPlaylist(Request $request): Response
    {
        if (($guard = $this->requireCapability('video.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $playlistId = (int) $request->param('id', '0');
        $videoId = (int) $request->input('video_id', '0');
        $service = new VideoService($this->app->db, $this->storageDir());

        if ($videoId > 0) {
            $service->addToPlaylist($playlistId, $videoId);
        }

        $playlist = $service->findPlaylist($playlistId);

        return Response::redirect('/videos/playlists/' . ($playlist['slug'] ?? ''));
    }

    public function removeFromPlaylist(Request $request): Response
    {
        if (($guard = $this->requireCapability('video.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new VideoService($this->app->db, $this->storageDir());
        $playlistId = (int) $request->param('id', '0');
        $service->removeFromPlaylist((int) $request->param('itemId', '0'));

        $playlist = $service->findPlaylist($playlistId);

        return Response::redirect('/videos/playlists/' . ($playlist['slug'] ?? ''));
    }

    public function movePlaylistItem(Request $request): Response
    {
        if (($guard = $this->requireCapability('video.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new VideoService($this->app->db, $this->storageDir());
        $playlistId = (int) $request->param('id', '0');
        $direction = (string) $request->input('direction', '');

        if (in_array($direction, ['up', 'down'], true)) {
            $service->moveItem($playlistId, (int) $request->param('itemId', '0'), $direction);
        }

        $playlist = $service->findPlaylist($playlistId);

        return Response::redirect('/videos/playlists/' . ($playlist['slug'] ?? ''));
    }

    /** @return array<int, array<string, mixed>> every video across every category, flattened — for the "add to playlist" picker */
    private function allVideosFlat(VideoService $service): array
    {
        $videos = [];
        foreach ($service->listCategories() as $category) {
            foreach ($service->listVideos((int) $category['id']) as $video) {
                $videos[] = $video;
            }
        }

        return $videos;
    }

    public function stream(Request $request): Response
    {
        $service = new VideoService($this->app->db, $this->storageDir());
        $video = $service->findVideo((int) $request->param('id', '0'));
        if ($video === null || $video['source_type'] !== 'upload') {
            return Response::notFound();
        }

        $path = $service->absolutePath($video);
        if (!is_file($path)) {
            return Response::notFound();
        }

        return Response::streamFile((string) file_get_contents($path), $video['mime_type']);
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
        return $this->app->rootDir . '/storage/uploads/video';
    }
}

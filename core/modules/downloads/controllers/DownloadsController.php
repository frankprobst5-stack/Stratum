<?php

declare(strict_types=1);

namespace Stratum\Modules\Downloads;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\SeoService;
use Stratum\Modules\Ratings\RatingService;
use Stratum\Modules\Users\AuthService;

final class DownloadsController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new DownloadService($this->app->db, $this->storageDir());

        $categories = array_map(
            fn (array $c): array => $c + ['files' => $service->listFiles((int) $c['id'])],
            $service->listCategories()
        );

        $content = $this->app->templates->render('downloads', 'index', [
            'categories' => $categories,
            'canUpload' => $this->app->auth->can('downloads.upload'),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function show(Request $request): Response
    {
        $service = new DownloadService($this->app->db, $this->storageDir());
        $file = $service->findFile((int) $request->param('id', '0'));
        if ($file === null) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $versions = array_map(
            fn (array $v): array => $v + ['uploaderName' => $this->uploaderName($authors, $v['uploader_id'] !== null ? (int) $v['uploader_id'] : null)],
            $service->listVersions((int) $file['id'])
        );

        $showRatings = $this->app->modules->isEnabled('ratings');
        $ratingSummary = null;
        $myRating = null;
        if ($showRatings) {
            $ratings = new RatingService($this->app->db);
            $ratingSummary = $ratings->summaryFor('download', (int) $file['id']);
            $currentUser = $this->app->auth->user();
            $myRating = $currentUser !== null ? $ratings->myRating('download', (int) $file['id'], (int) $currentUser['id']) : null;
        }

        $content = $this->app->templates->render('downloads', 'show', [
            'file' => $file,
            'versions' => $versions,
            'mirrors' => $service->listMirrors((int) $file['id']),
            'canUpload' => $this->app->auth->can('downloads.upload'),
            'canManage' => $this->app->auth->can('downloads.manage'),
            'isLoggedIn' => $this->app->auth->check(),
            'showRatings' => $showRatings,
            'canRate' => $showRatings && $this->app->auth->can('ratings.create'),
            'ratingSummary' => $ratingSummary,
            'myRating' => $myRating,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $file['title'],
            'description' => (new SeoService())->excerpt((string) ($file['description'] ?? '')),
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->requireCapability('downloads.upload')) !== null) {
            return $guard;
        }

        $service = new DownloadService($this->app->db, $this->storageDir());

        $content = $this->app->templates->render('downloads', 'form', [
            'categories' => $service->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->requireCapability('downloads.upload')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DownloadService($this->app->db, $this->storageDir());

        $categoryId = (int) $request->input('category_id', '0');
        $title = trim((string) $request->input('title', ''));
        $file = $request->file('file');

        if ($categoryId <= 0 || $title === '' || $file === null) {
            return Response::redirect('/downloads/create');
        }

        $validated = $service->validateUpload($file);
        if ($validated === null) {
            return Response::html('Invalid file — check the file type and size (max 10MB).', 422);
        }

        $user = $this->app->auth->user();
        $fileId = $service->createFile($categoryId, $title, (string) $request->input('description', ''), (int) $user['id'], $validated);

        return Response::redirect('/downloads/files/' . $fileId);
    }

    public function addVersion(Request $request): Response
    {
        if (($guard = $this->requireCapability('downloads.upload')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DownloadService($this->app->db, $this->storageDir());
        $fileId = (int) $request->param('id', '0');
        $file = $service->findFile($fileId);
        if ($file === null) {
            return Response::notFound();
        }

        $uploaded = $request->file('file');
        if ($uploaded === null) {
            return Response::redirect('/downloads/files/' . $fileId);
        }

        $validated = $service->validateUpload($uploaded);
        if ($validated === null) {
            return Response::html('Invalid file — check the file type and size (max 10MB).', 422);
        }

        $user = $this->app->auth->user();
        $service->addVersion($fileId, (int) $user['id'], $validated);

        return Response::redirect('/downloads/files/' . $fileId);
    }

    public function addMirror(Request $request): Response
    {
        if (($guard = $this->requireCapability('downloads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $fileId = (int) $request->param('id', '0');
        $label = trim((string) $request->input('label', ''));
        $url = trim((string) $request->input('url', ''));

        if ($label !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
            (new DownloadService($this->app->db, $this->storageDir()))->addMirror($fileId, $label, $url);
        }

        return Response::redirect('/downloads/files/' . $fileId);
    }

    public function deleteMirror(Request $request): Response
    {
        if (($guard = $this->requireCapability('downloads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new DownloadService($this->app->db, $this->storageDir()))->deleteMirror((int) $request->param('mirrorId', '0'));

        return Response::redirect('/downloads/files/' . (int) $request->param('id', '0'));
    }

    public function download(Request $request): Response
    {
        $service = new DownloadService($this->app->db, $this->storageDir());
        $file = $service->findFile((int) $request->param('id', '0'));
        if ($file === null) {
            return Response::notFound();
        }

        $version = $service->currentVersion((int) $file['id']);

        return $this->serveVersion($service, $file, $version);
    }

    public function downloadVersion(Request $request): Response
    {
        $service = new DownloadService($this->app->db, $this->storageDir());
        $file = $service->findFile((int) $request->param('id', '0'));
        if ($file === null) {
            return Response::notFound();
        }

        $version = $service->findVersion((int) $request->param('versionId', '0'));
        if ($version === null || (int) $version['file_id'] !== (int) $file['id']) {
            return Response::notFound();
        }

        return $this->serveVersion($service, $file, $version);
    }

    /** @param array<string, mixed>|null $version */
    private function serveVersion(DownloadService $service, array $file, ?array $version): Response
    {
        if ($version === null) {
            return Response::notFound();
        }

        if ($version['scan_status'] === 'infected') {
            return Response::html('This file failed a virus scan and is not available for download.', 403);
        }

        $path = $service->absolutePath($version);
        if (!is_file($path)) {
            return Response::notFound();
        }

        $service->incrementDownloadCount((int) $file['id']);

        return Response::file((string) file_get_contents($path), $version['mime_type'], $version['original_name']);
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

    private function uploaderName(AuthService $authors, ?int $userId): string
    {
        if ($userId === null) {
            return 'Unknown';
        }

        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/downloads';
    }
}

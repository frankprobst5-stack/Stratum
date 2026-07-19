<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class OrgGalleryController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'])) !== null) {
            return $guard;
        }

        $service = new OrgGalleryService($this->app->db, $this->storageDir());

        $content = $this->app->templates->render('org_spaces', 'gallery-index', [
            'org' => $org,
            'albums' => $service->listAlbums((int) $org['id']),
            'canManage' => $this->canManageOrg((int) $org['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createAlbum(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'], verifyCsrf: true, request: $request)) !== null) {
            return $guard;
        }

        $title = trim((string) $request->input('title', ''));
        $service = new OrgGalleryService($this->app->db, $this->storageDir());
        $validated = [];
        foreach ($request->files('photos') as $fileEntry) {
            $result = $service->validateUpload($fileEntry);
            if ($result !== null) {
                $validated[] = $result;
            }
        }

        if ($title === '' || $validated === []) {
            return Response::redirect('/organizations/' . $org['slug'] . '/gallery');
        }

        $user = $this->app->auth->user();
        $albumId = $service->createAlbum(
            (int) $org['id'],
            $title,
            (string) $request->input('description', ''),
            (int) $user['id'],
            $validated
        );

        return Response::redirect('/organizations/' . $org['slug'] . '/gallery/albums/' . $albumId);
    }

    public function album(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'])) !== null) {
            return $guard;
        }

        $service = new OrgGalleryService($this->app->db, $this->storageDir());
        $album = $service->findAlbum((int) $request->param('id', '0'));
        if ($album === null || (int) $album['org_id'] !== (int) $org['id']) {
            return Response::notFound();
        }

        $content = $this->app->templates->render('org_spaces', 'gallery-album', [
            'org' => $org,
            'album' => $album,
            'photos' => $service->listPhotos((int) $album['id']),
            'canManage' => $this->canManageOrg((int) $org['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function image(Request $request): Response
    {
        return $this->serveImage($request, useThumbnail: false);
    }

    public function thumbnail(Request $request): Response
    {
        return $this->serveImage($request, useThumbnail: true);
    }

    public function deletePhoto(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $service = new OrgGalleryService($this->app->db, $this->storageDir());
        $photo = $service->findPhoto((int) $request->param('id', '0'));
        if ($photo !== null) {
            $album = $service->findAlbum((int) $photo['album_id']);
            if ($album !== null && (int) $album['org_id'] === (int) $org['id']) {
                $service->softDeletePhoto((int) $photo['id']);

                return Response::redirect('/organizations/' . $org['slug'] . '/gallery/albums/' . $album['id']);
            }
        }

        return Response::redirect('/organizations/' . $org['slug'] . '/gallery');
    }

    public function deleteAlbum(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $service = new OrgGalleryService($this->app->db, $this->storageDir());
        $album = $service->findAlbum((int) $request->param('id', '0'));
        if ($album !== null && (int) $album['org_id'] === (int) $org['id']) {
            $service->softDeleteAlbum((int) $album['id']);
        }

        return Response::redirect('/organizations/' . $org['slug'] . '/gallery');
    }

    private function serveImage(Request $request, bool $useThumbnail): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'])) !== null) {
            return $guard;
        }

        $service = new OrgGalleryService($this->app->db, $this->storageDir());
        $photo = $service->findPhoto((int) $request->param('id', '0'));
        if ($photo === null) {
            return Response::notFound();
        }

        $album = $service->findAlbum((int) $photo['album_id']);
        if ($album === null || (int) $album['org_id'] !== (int) $org['id']) {
            return Response::notFound();
        }

        $path = $useThumbnail ? $service->absoluteThumbnailPath($photo) : $service->absolutePath($photo);
        if (!is_file($path)) {
            return Response::notFound();
        }

        $contentType = $useThumbnail ? 'image/jpeg' : $photo['mime_type'];

        return Response::streamFile((string) file_get_contents($path), $contentType);
    }

    /** @return array<string, mixed>|Response */
    private function requireActiveOrg(Request $request): array|Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null || !$org['is_active']) {
            return Response::notFound();
        }

        return $org;
    }

    private function requireMember(int $orgId, bool $verifyCsrf = false, ?Request $request = null): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->app->auth->user();
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        if (!$service->isMember((int) $user['id'], $orgId) && !$this->canManageOrg($orgId)) {
            return Response::forbidden();
        }

        if ($verifyCsrf && !$this->app->session->verifyCsrf($request?->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        return null;
    }

    private function requireManage(int $orgId, Request $request): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->canManageOrg($orgId)) {
            return Response::forbidden();
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        return null;
    }

    private function canManageOrg(int $orgId): bool
    {
        return $this->app->auth->can('org_spaces.moderate', 'org', $orgId);
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/org_spaces_gallery';
    }
}

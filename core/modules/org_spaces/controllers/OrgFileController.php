<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class OrgFileController
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

        $service = new OrgFileService($this->app->db, $this->storageDir());
        $authors = new AuthService($this->app->db);

        $files = array_map(function (array $file) use ($authors): array {
            $uploaderId = $file['uploader_id'] !== null ? (int) $file['uploader_id'] : null;
            $file['uploaderName'] = $uploaderId !== null ? ($authors->findById($uploaderId)['username'] ?? 'Unknown') : 'Unknown';

            return $file;
        }, $service->listFiles((int) $org['id']));

        $content = $this->app->templates->render('org_spaces', 'files-index', [
            'org' => $org,
            'files' => $files,
            'canManage' => $this->canManageOrg((int) $org['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function upload(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'], verifyCsrf: true, request: $request)) !== null) {
            return $guard;
        }

        $title = trim((string) $request->input('title', ''));
        $fileEntry = $request->file('file');
        if ($title === '' || $fileEntry === null) {
            return Response::redirect('/organizations/' . $org['slug'] . '/files');
        }

        $service = new OrgFileService($this->app->db, $this->storageDir());
        $validated = $service->validateUpload($fileEntry);
        if ($validated === null) {
            return Response::html('That file type isn\'t allowed, or it\'s too large (10MB max).', 422);
        }

        $user = $this->app->auth->user();
        $service->storeFile(
            (int) $org['id'],
            (int) $user['id'],
            $title,
            (string) $request->input('description', ''),
            $validated
        );

        return Response::redirect('/organizations/' . $org['slug'] . '/files');
    }

    public function download(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'])) !== null) {
            return $guard;
        }

        $service = new OrgFileService($this->app->db, $this->storageDir());
        $file = $service->findFile((int) $request->param('id', '0'));
        if ($file === null || (int) $file['org_id'] !== (int) $org['id']) {
            return Response::notFound();
        }

        $path = $service->absolutePath($file);
        if (!is_file($path)) {
            return Response::notFound();
        }

        $service->incrementDownloadCount((int) $file['id']);

        return Response::file((string) file_get_contents($path), $file['mime_type'], $file['original_name']);
    }

    public function delete(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $service = new OrgFileService($this->app->db, $this->storageDir());
        $file = $service->findFile((int) $request->param('id', '0'));
        if ($file !== null && (int) $file['org_id'] === (int) $org['id']) {
            $service->softDeleteFile((int) $file['id']);
        }

        return Response::redirect('/organizations/' . $org['slug'] . '/files');
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
        return $this->app->rootDir . '/storage/uploads/org_spaces_files';
    }
}

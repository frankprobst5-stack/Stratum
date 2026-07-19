<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\UpdateApplier;
use Stratum\Core\UpdateChecker;
use Stratum\Core\UpdatePackageException;
use Stratum\Core\UpdatePackageVerifier;

final class SystemUpdateController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('system.update')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'system-update', [
            'currentVersion' => $this->currentVersion(),
            'maxUploadSize' => $this->maxUploadSize(),
            'updateCheckUrl' => $this->getSetting('update_check_url', ''),
            'checkResult' => null,
            'csrfToken' => $this->app->session->csrfToken(),
            'result' => null,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function checkForUpdate(Request $request): Response
    {
        if (($guard = $this->guard('system.update')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $url = trim((string) $request->input('update_check_url', ''));
        $this->setSetting('update_check_url', $url);

        $checkResult = $url !== ''
            ? (new UpdateChecker())->check($url, $this->currentVersion())
            : null;

        $content = $this->app->templates->render('admin', 'system-update', [
            'currentVersion' => $this->currentVersion(),
            'maxUploadSize' => $this->maxUploadSize(),
            'updateCheckUrl' => $url,
            'checkResult' => $checkResult,
            'csrfToken' => $this->app->session->csrfToken(),
            'result' => null,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function upload(Request $request): Response
    {
        if (($guard = $this->guard('system.update')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $fileEntry = $request->file('package');
        $result = null;

        if ($fileEntry === null) {
            // PHP empties $_FILES entirely (rather than reporting an error code) when
            // post_max_size is exceeded, so this exact message is indistinguishable
            // from "no file was chosen" — mention the limit either way since a
            // too-large package is the far more likely real cause here.
            $result = [
                'success' => false,
                'message' => 'No file was received. If your package is close to or larger than '
                    . $this->maxUploadSize() . ', that\'s likely why — this server\'s upload limit may need raising.',
                'steps' => [],
            ];
        } else {
            $result = $this->processUpload($fileEntry);
        }

        $content = $this->app->templates->render('admin', 'system-update', [
            'currentVersion' => $this->currentVersion(),
            'maxUploadSize' => $this->maxUploadSize(),
            'updateCheckUrl' => $this->getSetting('update_check_url', ''),
            'checkResult' => null,
            'csrfToken' => $this->app->session->csrfToken(),
            'result' => $result,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fileEntry
     * @return array{success: bool, message: string, steps: array<int, array{label: string, ok: bool}>}
     */
    private function processUpload(array $fileEntry): array
    {
        $rootDir = $this->app->rootDir;
        $stagingDir = $rootDir . '/storage/update-staging';
        $uploadedZipPath = $rootDir . '/storage/update-staging-upload.zip';

        if (!is_uploaded_file($fileEntry['tmp_name'])) {
            return ['success' => false, 'message' => 'Upload failed.', 'steps' => []];
        }
        move_uploaded_file($fileEntry['tmp_name'], $uploadedZipPath);

        try {
            $verifier = new UpdatePackageVerifier($rootDir, $rootDir . '/core/update-public.key');
            $manifest = $verifier->verifyAndStage($uploadedZipPath, $stagingDir);

            $applier = new UpdateApplier($rootDir, $this->app->db);

            return $applier->apply($manifest, $stagingDir);
        } catch (UpdatePackageException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'steps' => []];
        } finally {
            if (is_file($uploadedZipPath)) {
                unlink($uploadedZipPath);
            }
        }
    }

    private function getSetting(string $key, string $default): string
    {
        $row = $this->app->db->fetchOne(
            'SELECT `value` FROM ' . $this->app->db->table('core_settings') . ' WHERE `key` = :key',
            ['key' => $key]
        );

        return $row['value'] ?? $default;
    }

    private function setSetting(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        $table = $this->app->db->table('core_settings');

        $existing = $this->app->db->fetchOne("SELECT id FROM {$table} WHERE `key` = :key", ['key' => $key]);

        if ($existing === null) {
            $this->app->db->insert('core_settings', [
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $this->app->db->execute(
            "UPDATE {$table} SET `value` = :value, updated_at = :now WHERE `key` = :key",
            ['value' => $value, 'now' => $now, 'key' => $key]
        );
    }

    private function currentVersion(): string
    {
        return trim((string) @file_get_contents($this->app->rootDir . '/VERSION')) ?: 'unknown';
    }

    private function maxUploadSize(): string
    {
        // The effective cap is whichever ini setting is smaller — showing both would
        // just invite the admin to misread the wrong one as the actual limit.
        $upload = self::toBytes(ini_get('upload_max_filesize') ?: '2M');
        $post = self::toBytes(ini_get('post_max_size') ?: '8M');

        return self::fromBytes(min($upload, $post));
    }

    private static function toBytes(string $iniValue): int
    {
        $value = (int) $iniValue;
        return match (strtoupper(substr(trim($iniValue), -1))) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }

    private static function fromBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / (1024 * 1024), 1) . 'MB'
            : round($bytes / 1024, 1) . 'KB';
    }
}

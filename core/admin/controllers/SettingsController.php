<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\FileUploadValidator;
use Stratum\Core\FontStacks;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\Translator;

final class SettingsController extends AdminController
{
    private const MAX_BANNER_SIZE = 5 * 1024 * 1024; // 5MB — a single header graphic, generous but bounded

    /**
     * Width:height floor for the banner as displayed (layout.php renders
     * it at `width: 100%; height: auto` — the whole image, never
     * cropped by CSS, see the 2026-07-18 design notes). An image
     * narrower than this ratio (tall/square/portrait) would make the
     * masthead disproportionately tall on a wide page, so anything below
     * the floor gets auto-cropped to it at upload time, rather than an
     * admin needing to pre-crop their own art by hand. 5.5 confirmed by
     * eye against the user's own real banner art (hexagon logo +
     * wordmark + tagline stayed fully intact, only the least-essential
     * decorative icon row got trimmed) — not a guess.
     */
    private const MIN_BANNER_RATIO = 5.5;

    /**
     * The crop isn't centered — a pure 50/50 top/bottom split risks
     * cutting into whatever sits near the top (a logo mark, typically)
     * for the sake of symmetry. Weighting most of the removed height
     * toward the bottom (confirmed by eye: 30% top / 70% bottom kept the
     * logo and full wordmark intact, only trimming a decorative icon
     * row at the very bottom) protects the part of the art that
     * actually matters.
     */
    private const BANNER_CROP_TOP_BIAS = 0.30;

    /** @var array<string, string> detected MIME type => stored file extension — same allow-list shape every other image upload in this app uses */
    private const ALLOWED_BANNER_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function index(Request $request): Response
    {
        if (($guard = $this->guard('settings.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'settings', [
            'siteName' => $this->getSetting('site_name', 'Stratum CMS'),
            'siteDescription' => $this->getSetting('site_description', ''),
            'ogDefaultImage' => $this->getSetting('og_default_image', ''),
            'maintenanceMode' => $this->getSetting('maintenance_mode', '0') === '1',
            'maintenanceMessage' => $this->getSetting('maintenance_message', ''),
            'siteLanguage' => $this->getSetting('site_language', 'en'),
            'availableLanguages' => Translator::availableLanguages($this->app->rootDir . '/lang'),
            'headerBannerExt' => $this->getSetting('header_banner_ext', ''),
            'accentColor' => $this->getSetting('theme_accent_color', '#2f6fed'),
            'fontStack' => $this->getSetting('theme_font_stack', 'system'),
            'fontStackOptions' => FontStacks::OPTIONS,
            'darkMode' => $this->getSetting('theme_dark_mode', 'off'),
            'csrfToken' => $this->app->session->csrfToken(),
            'saved' => $request->query('saved') === '1',
            'bannerError' => $request->query('banner_error') === '1',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function update(Request $request): Response
    {
        if (($guard = $this->guard('settings.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $this->setSetting('site_name', (string) $request->input('site_name', 'Stratum CMS'));
        $this->setSetting('site_description', (string) $request->input('site_description', ''));
        $this->setSetting('og_default_image', (string) $request->input('og_default_image', ''));
        $this->setSetting('maintenance_mode', $request->input('maintenance_mode') === '1' ? '1' : '0');
        $this->setSetting('maintenance_message', (string) $request->input('maintenance_message', ''));

        $availableLanguages = Translator::availableLanguages($this->app->rootDir . '/lang');
        $requestedLanguage = (string) $request->input('site_language', 'en');
        $this->setSetting('site_language', array_key_exists($requestedLanguage, $availableLanguages) ? $requestedLanguage : 'en');

        // Rejected input falls back to the default rather than erroring —
        // matches site_language's own "invalid code silently falls back to
        // en" precedent just above, appropriate for a cosmetic setting
        // with no security stakes.
        $accentColor = strtolower(trim((string) $request->input('theme_accent_color', '#2f6fed')));
        $this->setSetting('theme_accent_color', preg_match('/^#[0-9a-f]{6}$/', $accentColor) === 1 ? $accentColor : '#2f6fed');

        $fontStack = (string) $request->input('theme_font_stack', 'system');
        $this->setSetting('theme_font_stack', FontStacks::isValid($fontStack) ? $fontStack : 'system');

        $darkMode = (string) $request->input('theme_dark_mode', 'off');
        $this->setSetting('theme_dark_mode', in_array($darkMode, ['off', 'on', 'auto'], true) ? $darkMode : 'off');

        return Response::redirect('/admin/settings?saved=1');
    }

    /**
     * Uploads a custom header banner, replacing the built-in default
     * (`/assets/images/logo-wide.png`) — the "admin-uploadable banner
     * image" requirement from the Stage 8 header/masthead design note.
     * Stored outside the webroot (`storage/uploads/site/`), same model
     * every other uploaded image in this app already uses (gallery,
     * downloads, etc.), served back out via the public `/site/header-
     * banner` route rather than a static public path. Fixed base filename
     * (`header-banner.{ext}`) — only one can ever exist, so a re-upload
     * with a different extension deletes the old file first rather than
     * accumulating orphans.
     */
    public function uploadHeaderBanner(Request $request): Response
    {
        if (($guard = $this->guard('settings.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $fileEntry = $request->file('banner');
        if ($fileEntry === null) {
            return Response::redirect('/admin/settings');
        }

        $validator = new FileUploadValidator(self::MAX_BANNER_SIZE, self::ALLOWED_BANNER_MIME_EXTENSIONS);
        $validated = $validator->validate($fileEntry);
        if ($validated === null) {
            return Response::redirect('/admin/settings?banner_error=1');
        }

        $this->deleteExistingBannerFile();

        $dir = $this->storageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $destPath = "{$dir}/header-banner.{$validated['extension']}";
        move_uploaded_file($validated['tmpPath'], $destPath);
        $this->autoCropToMinRatio($destPath, $validated['mimeType']);
        $this->setSetting('header_banner_ext', $validated['extension']);

        return Response::redirect('/admin/settings?saved=1');
    }

    /** Deletes the custom banner (if any) and reverts to the built-in default. */
    public function revertHeaderBanner(Request $request): Response
    {
        if (($guard = $this->guard('settings.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $this->deleteExistingBannerFile();
        $this->setSetting('header_banner_ext', '');

        return Response::redirect('/admin/settings?saved=1');
    }

    /**
     * Crops $path in place (down to MIN_BANNER_RATIO, biased toward
     * keeping the top per BANNER_CROP_TOP_BIAS) if its natural aspect
     * ratio is narrower than that floor — a no-op for anything already
     * wide enough, which is the common case and preserves the whole
     * image untouched, same as before this existed. Uses GD directly,
     * same library `ImageThumbnailer` already uses elsewhere in this
     * app; kept local here rather than promoted to a shared service
     * since this is a crop-to-ratio-with-a-bias operation, not a
     * generic resize, and nothing else in the app needs that yet.
     */
    private function autoCropToMinRatio(string $path, string $mimeType): void
    {
        $dimensions = @getimagesize($path);
        if ($dimensions === false) {
            return;
        }

        [$width, $height] = $dimensions;
        if ($width / $height >= self::MIN_BANNER_RATIO) {
            return;
        }

        $source = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if ($source === false) {
            return;
        }

        $targetHeight = (int) round($width / self::MIN_BANNER_RATIO);
        $cropTop = (int) round(($height - $targetHeight) * self::BANNER_CROP_TOP_BIAS);

        $cropped = imagecreatetruecolor($width, $targetHeight);
        if ($mimeType === 'image/png') {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
        }
        imagecopy($cropped, $source, 0, 0, 0, $cropTop, $width, $targetHeight);

        match ($mimeType) {
            'image/jpeg' => imagejpeg($cropped, $path, 90),
            'image/png' => imagepng($cropped, $path),
            'image/webp' => imagewebp($cropped, $path),
            default => null,
        };

        imagedestroy($cropped);
        imagedestroy($source);
    }

    private function deleteExistingBannerFile(): void
    {
        $existingExt = $this->getSetting('header_banner_ext', '');
        if ($existingExt === '') {
            return;
        }

        $path = "{$this->storageDir()}/header-banner.{$existingExt}";
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/site';
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
}

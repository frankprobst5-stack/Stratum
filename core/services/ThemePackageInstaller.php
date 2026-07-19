<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Installs/removes admin-uploaded themes — same shape and reasoning as
 * AddonPackageInstaller, mirrored for `theme.json` instead of
 * `module.json`. Custom themes live in `storage/themes/{id}/`,
 * deliberately NOT under the app's own `themes/` directory — the signed
 * Update Mechanism's ALLOWED_PREFIXES includes `themes/`, so a developer-
 * shipped update package could in principle touch anything under it; a
 * club's own uploaded theme must stay somewhere no future official
 * update can ever reach.
 */
final class ThemePackageInstaller
{
    private const ID_PATTERN = '/^[a-z][a-z0-9_-]*$/';

    public function __construct(
        private readonly string $coreThemesDir,
        private readonly string $customThemesDir
    ) {
    }

    /** Returns the new theme's id on success. */
    public function install(string $zipPath): string
    {
        $stagingDir = $this->customThemesDir . '/.staging-' . bin2hex(random_bytes(8));
        $extractor = new SafeZipExtractor();

        $result = $extractor->extractValidated($zipPath, 'theme.json', self::ID_PATTERN, $stagingDir);
        $id = $result['id'];
        $parent = $result['manifest']['parent'] ?? null;

        if (is_dir("{$this->coreThemesDir}/{$id}") || is_dir("{$this->customThemesDir}/{$id}")) {
            $extractor->removeDirectory($stagingDir);
            throw new PackageInstallException("A theme with the id \"{$id}\" already exists — remove it first or choose a different id in theme.json.");
        }

        // A theme that declares a `parent` is allowed to ship no
        // templates/layout.php of its own at all — TemplateEngine::
        // renderLayout() falls back to the parent's — but the declared
        // parent has to actually exist as a built-in theme (the only kind
        // TemplateEngine ever resolves a parent against), or this would
        // defer a real error from install time to the first page render.
        if (is_string($parent) && $parent !== '') {
            if (!is_file("{$this->coreThemesDir}/{$parent}/theme.json")) {
                $extractor->removeDirectory($stagingDir);
                throw new PackageInstallException("This theme declares parent \"{$parent}\", but no built-in theme with that id exists — a child theme's parent must be a built-in theme.");
            }
        } elseif (!is_file("{$stagingDir}/templates/layout.php")) {
            $extractor->removeDirectory($stagingDir);
            throw new PackageInstallException('This theme is missing templates/layout.php — every theme must provide one, unless it declares a "parent" to inherit from.');
        }

        // $this->customThemesDir is guaranteed to exist here — extraction
        // above already created it (recursively) as the staging dir's parent.
        rename($stagingDir, "{$this->customThemesDir}/{$id}");

        return $id;
    }

    /**
     * Scaffolds a lean child theme directly on disk — no zip round-trip.
     * Ships only theme.json (parent set, no templates/layout.php at all,
     * inheriting the parent's via TemplateEngine::renderLayout()'s
     * fallback) plus an empty overrides/ dir, so it's immediately
     * activatable and byte-for-byte identical to its parent until the
     * admin adds real overrides/{module}/{template}.php files later.
     * $parentId must already be a real built-in theme, checked by the
     * caller (ThemesController) against ThemeManager's own list.
     */
    public function createChild(string $id, string $name, string $description, string $parentId): void
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new PackageInstallException('Theme id must be lowercase letters, numbers, and underscores only, and start with a letter.');
        }

        if (is_dir("{$this->coreThemesDir}/{$id}") || is_dir("{$this->customThemesDir}/{$id}")) {
            throw new PackageInstallException("A theme with the id \"{$id}\" already exists — choose a different id.");
        }

        $dir = "{$this->customThemesDir}/{$id}";
        mkdir("{$dir}/overrides", 0755, true);
        touch("{$dir}/overrides/.gitkeep");

        file_put_contents("{$dir}/theme.json", json_encode([
            'id' => $id,
            'name' => $name !== '' ? $name : $id,
            'version' => '1.0.0',
            'parent' => $parentId,
            'description' => $description,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** True if a custom theme existed with this id and was removed. Refuses to touch anything outside the custom themes directory. */
    public function remove(string $id): bool
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            return false;
        }

        $path = "{$this->customThemesDir}/{$id}";
        if (!is_dir($path)) {
            return false;
        }

        (new SafeZipExtractor())->removeDirectory($path);

        return true;
    }
}

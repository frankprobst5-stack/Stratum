<?php

declare(strict_types=1);

namespace Stratum\Core;

final class TemplateEngine
{
    private ?string $themeParent = null;
    private string $activeThemeRoot;

    /**
     * $customThemesDir is optional (mirrors ModuleManager's
     * $customModulesDir) — admin-uploaded themes (ThemePackageInstaller)
     * live there. Only the *active* theme is ever resolved against it;
     * a child theme's `parent` is deliberately still resolved against
     * the built-in $themesDir only — the established mental model (see
     * docs/roadmap.md's Stage 8 "child themes" note) is a custom theme
     * extending a shipped one, not custom-extending-custom, and that
     * constraint is simple enough to be worth keeping for now rather
     * than generalizing before anything needs it.
     */
    public function __construct(
        private readonly string $themesDir,
        private readonly string $coreModulesDir,
        private readonly string $coreAdminDir,
        private readonly string $activeTheme,
        private readonly ?string $customThemesDir = null,
        private readonly ?string $customModulesDir = null
    ) {
        $this->activeThemeRoot = $this->resolveActiveThemeRoot();

        $themeJsonPath = "{$this->activeThemeRoot}/{$this->activeTheme}/theme.json";
        if (is_file($themeJsonPath)) {
            $meta = json_decode((string) file_get_contents($themeJsonPath), true);
            $this->themeParent = is_array($meta) ? ($meta['parent'] ?? null) : null;
        }
    }

    /** Prefers a custom (uploaded) theme over a built-in one of the same name, though AddonPackageInstaller/ThemePackageInstaller already refuse to let that collision happen at upload time. */
    private function resolveActiveThemeRoot(): string
    {
        if ($this->customThemesDir !== null && is_dir("{$this->customThemesDir}/{$this->activeTheme}")) {
            return $this->customThemesDir;
        }

        return $this->themesDir;
    }

    /**
     * Renders a module (or 'admin') template, honoring the theme override chain.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $moduleId, string $template, array $data = []): string
    {
        return $this->capture($this->resolve($moduleId, $template), $data);
    }

    /**
     * Renders the theme's base layout directly (not subject to the
     * per-module override chain — it *is* the theme). A child theme is
     * allowed to ship no `templates/layout.php` of its own at all and
     * simply inherit its parent's, the same "override only what you want
     * to change" idea `resolve()` already applies to individual module
     * templates — without this fallback a genuinely lean child theme
     * (declares a `parent`, ships only `overrides/`) would 500 on every
     * request instead of rendering the parent's layout untouched.
     *
     * @param array<string, mixed> $data
     */
    public function renderLayout(array $data = []): string
    {
        $path = "{$this->activeThemeRoot}/{$this->activeTheme}/templates/layout.php";
        if (!is_file($path) && $this->themeParent !== null) {
            $path = "{$this->themesDir}/{$this->themeParent}/templates/layout.php";
        }

        if (!is_file($path)) {
            throw new \RuntimeException("Layout not found for theme '{$this->activeTheme}'");
        }

        return $this->capture($path, $data);
    }

    /**
     * Renders the admin panel's own chrome directly — not subject to the
     * theme override chain (same reasoning as renderLayout(): this *is*
     * the admin shell, not public-facing themeable content) and not
     * per-install swappable the way the public theme is, since the admin
     * panel isn't something a club reskins.
     *
     * @param array<string, mixed> $data
     */
    public function renderAdminLayout(array $data = []): string
    {
        return $this->capture($this->coreAdminDir . '/templates/admin-layout.php', $data);
    }

    private function resolve(string $moduleId, string $template): string
    {
        $relative = "{$moduleId}/{$template}.php";

        $themeOverride = "{$this->activeThemeRoot}/{$this->activeTheme}/overrides/{$relative}";
        if (is_file($themeOverride)) {
            return $themeOverride;
        }

        if ($this->themeParent !== null) {
            $parentOverride = "{$this->themesDir}/{$this->themeParent}/overrides/{$relative}";
            if (is_file($parentOverride)) {
                return $parentOverride;
            }
        }

        $defaultBase = $moduleId === 'admin' ? $this->coreAdminDir : "{$this->coreModulesDir}/{$moduleId}";
        $defaultPath = "{$defaultBase}/templates/{$template}.php";
        if (is_file($defaultPath)) {
            return $defaultPath;
        }

        // Built-in modules live under $coreModulesDir; an uploaded addon
        // (AddonPackageInstaller) doesn't, so its own templates would
        // never be found without also checking $customModulesDir here —
        // same directory ModuleManager's own $customModulesDir points at.
        if ($this->customModulesDir !== null) {
            $customPath = "{$this->customModulesDir}/{$moduleId}/templates/{$template}.php";
            if (is_file($customPath)) {
                return $customPath;
            }
        }

        throw new \RuntimeException("Template not found: {$moduleId}/{$template}");
    }

    /** @param array<string, mixed> $data */
    private function capture(string $path, array $data): string
    {
        $render = function (string $__path, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__path;

            return (string) ob_get_clean();
        };

        return $render($path, $data);
    }
}

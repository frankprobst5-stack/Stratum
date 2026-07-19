<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Minimal service container — not a DI framework, just the set of shared
 * singletons bootstrapped once in public/index.php / bin/install.php and
 * handed to modules' routes.php files. See docs/architecture.md ("no
 * framework") for why this stays a plain readonly bag rather than growing
 * autowiring, tagging, etc.
 */
final class App
{
    public function __construct(
        public readonly string $rootDir,
        public readonly Config $config,
        public readonly Database $db,
        public readonly Session $session,
        public readonly Auth $auth,
        public readonly Router $router,
        public readonly HookRegistry $hooks,
        public readonly BlockRegistry $blocks,
        public readonly TemplateEngine $templates,
        public readonly Logger $logger,
        public readonly ModuleManager $modules,
        public readonly PermissionEngine $permissions,
    ) {
    }

    /**
     * Wraps page content in the appropriate layout with nav/auth state
     * filled in. Small, concrete convenience method (not a growing "helpers"
     * dumping ground) — every controller needs exactly this, so it lives
     * once here rather than being copy-pasted per controller.
     *
     * Auto-detects admin vs. public by path (2026-07-17) rather than
     * requiring all ~20 admin controllers to call a separate method —
     * every admin controller already calls this exact method with this
     * exact signature, so branching here means the new admin chrome
     * (docs/roadmap.md's "modernized e107" direction) applies everywhere
     * under /admin automatically, with zero risk of a controller being
     * missed.
     *
     * $seo is optional and additive (2026-07-17, Built-in SEO) — every one
     * of the ~50 existing call sites keeps working unchanged and gets the
     * same sane site-wide defaults it always rendered with; only public
     * content-detail pages (articles, wiki, etc.) bother passing per-page
     * overrides. Admin pages never reach the SEO-tag branch at all, since
     * they return early into renderAdminLayout() above.
     *
     * @param array{title?: ?string, description?: ?string, canonical?: ?string, ogType?: string, ogImage?: ?string} $seo
     */
    public function renderPage(string $content, Request $request, array $seo = []): string
    {
        $settingsRows = $this->db->fetchAll(
            'SELECT `key`, `value` FROM ' . $this->db->table('core_settings')
                . " WHERE `key` IN ('site_name', 'site_description', 'og_default_image', 'header_banner_ext', 'theme_accent_color', 'theme_font_stack', 'theme_dark_mode')"
        );
        $settings = array_column($settingsRows, 'value', 'key');
        $siteName = $settings['site_name'] ?? 'Stratum CMS';

        // Re-validated here too (not just at save time in
        // SettingsController::update()) — cheap defense-in-depth against
        // a malformed value ever reaching a raw <style> block, the same
        // "don't trust it just because it's already in the DB" posture
        // this app applies anywhere untrusted-shaped data reaches HTML/CSS
        // output directly.
        $accentColor = $settings['theme_accent_color'] ?? '#2f6fed';
        if (preg_match('/^#[0-9a-f]{6}$/i', $accentColor) !== 1) {
            $accentColor = '#2f6fed';
        }
        $fontStackCss = FontStacks::cssFor($settings['theme_font_stack'] ?? 'system');
        $darkMode = $settings['theme_dark_mode'] ?? 'off';
        if (!in_array($darkMode, ['off', 'on', 'auto'], true)) {
            $darkMode = 'off';
        }

        if (str_starts_with($request->path(), '/admin')) {
            $moduleAdminNav = array_values(array_filter(
                $this->modules->adminNavItems(),
                fn (array $item): bool => $this->auth->can($item['capability'])
            ));

            return $this->templates->renderAdminLayout([
                'content' => $content,
                'siteName' => $siteName,
                'currentUser' => $this->auth->user(),
                'moduleAdminNav' => $moduleAdminNav,
                'currentPath' => $request->path(),
                'csrfToken' => $this->session->csrfToken(),
            ]);
        }

        $ogImage = $seo['ogImage'] ?? (($settings['og_default_image'] ?? '') !== '' ? $settings['og_default_image'] : null);
        if ($ogImage !== null && !str_starts_with($ogImage, 'http://') && !str_starts_with($ogImage, 'https://')) {
            // Controllers pass route-relative paths (e.g. a listing's own
            // thumbnail route) — only the site-wide default is expected to
            // already be a full URL, since that one's typed in by an admin.
            $ogImage = $request->baseUrl() . $ogImage;
        }

        // /search is deliberately excluded from the menu builder entirely
        // (never synced, never shown in either bucket) — it already gets
        // its own dedicated icon in the topbar_actions region, the same
        // exclusion layout.php itself used to hardcode before this
        // feature existed.
        $navigableModuleItems = array_values(array_filter(
            $this->modules->navItems(),
            static fn (array $item): bool => $item['route'] !== '/search'
        ));
        $navMenu = (new NavMenuService($this->db))->orderedItems($navigableModuleItems);

        return $this->templates->renderLayout([
            'content' => $content,
            'siteName' => $siteName,
            'primaryNav' => $navMenu['primary'],
            'moreNav' => $navMenu['more'],
            'guestNav' => $this->modules->guestNavItems(),
            'currentPath' => $request->path(),
            'isLoggedIn' => $this->auth->check(),
            'isAdmin' => $this->auth->can('admin.access'),
            'currentUser' => $this->auth->user(),
            'csrfToken' => $this->session->csrfToken(),
            'blocks' => $this->blocks,
            'pageTitle' => $seo['title'] ?? null,
            'metaDescription' => $seo['description'] ?? ($settings['site_description'] ?? ''),
            'canonicalUrl' => $seo['canonical'] ?? ($request->baseUrl() . $request->path()),
            'ogType' => $seo['ogType'] ?? 'website',
            'ogImage' => $ogImage,
            // Custom upload if an admin has set one, else the built-in default
            // brand art — same "admin can turn this off/customize it" spirit
            // as every other themeable image in this app.
            'headerBannerUrl' => ($settings['header_banner_ext'] ?? '') !== ''
                ? '/site/header-banner'
                : '/assets/images/logo-wide.png',
            'accentColor' => $accentColor,
            'fontStackCss' => $fontStackCss,
            'darkMode' => $darkMode,
        ]);
    }

    /**
     * Fires the 'notify' hook with a notification event payload and logs any
     * listener failures. Same "every producer needs exactly this" reasoning
     * as renderPage() above: HookRegistry::fire() isolates listener
     * exceptions into a return value that producers would otherwise silently
     * discard. If the notifications module is disabled, no listener is
     * registered and this is a harmless no-op — producers never need to
     * check whether notifications are enabled.
     *
     * @param array<string, mixed> $event
     */
    public function notify(array $event): void
    {
        foreach ($this->hooks->fire('notify', $event) as $error) {
            $this->logger->error('notify listener failed: ' . $error->getMessage());
        }
    }
}

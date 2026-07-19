<?php
/**
 * @var string $siteName
 * @var string $siteDescription
 * @var string $ogDefaultImage
 * @var bool $maintenanceMode
 * @var string $maintenanceMessage
 * @var string $siteLanguage
 * @var array<string, string> $availableLanguages code => display name
 * @var string $headerBannerExt empty string if no custom banner is set
 * @var string $accentColor hex, e.g. "#2f6fed"
 * @var string $fontStack key into Stratum\Core\FontStacks::OPTIONS
 * @var array<string, array{label: string, css: string}> $fontStackOptions
 * @var string $darkMode 'off' | 'on' | 'auto'
 * @var string $csrfToken
 * @var bool $saved
 * @var bool $bannerError
 */
?>
<h1>Site Settings</h1>

<?php if ($saved): ?>
    <p style="color:#0a7d2c;">Saved.</p>
<?php endif; ?>
<?php if ($bannerError): ?>
    <p style="color:#c0392b;">That image couldn't be used — JPEG, PNG, or WebP only, up to 5MB.</p>
<?php endif; ?>

<form method="post" action="<?= e(route('/admin/settings')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="site_name">Site name</label><br>
        <input type="text" id="site_name" name="site_name" value="<?= e($siteName) ?>" required>
    </p>

    <p>
        <label for="site_description">Site description</label><br>
        <textarea id="site_description" name="site_description" rows="2" cols="60"><?= e($siteDescription) ?></textarea><br>
        <small>Used as the default meta description and social preview text on any page that doesn't set its own.</small>
    </p>

    <p>
        <label for="og_default_image">Default social share image URL</label><br>
        <input type="text" id="og_default_image" name="og_default_image" value="<?= e($ogDefaultImage) ?>" size="60" placeholder="https://example.org/assets/images/og-default.png"><br>
        <small>Shown when a page (e.g. an article) has no image of its own to share.</small>
    </p>

    <h2>Header Banner</h2>
    <p style="color:#666;">
        Shown at the top of every public page. Replace it with your own
        club's art any time — JPEG, PNG, or WebP, up to 5MB. Revert to
        bring back the default Stratum banner.
    </p>
    <p>
        <img
            src="<?= $headerBannerExt !== '' ? e(route('/site/header-banner')) : '/assets/images/logo-wide.png' ?>"
            alt="Current header banner" style="max-width:100%; max-height:150px; display:block; background:#0a0d16; padding:0.5rem; border-radius:4px;">
    </p>
    </form>

    <form method="post" action="<?= e(route('/admin/settings/header-banner')) ?>" enctype="multipart/form-data" style="margin-bottom:1rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit">Upload</button>
    </form>

    <?php if ($headerBannerExt !== ''): ?>
        <form method="post" action="<?= e(route('/admin/settings/header-banner/revert')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Revert to default</button>
        </form>
    <?php endif; ?>

    <form method="post" action="<?= e(route('/admin/settings')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <h2>Colors &amp; Typography</h2>
    <p style="color:#666;">
        Applies to the public site's own chrome (header, nav, primary
        buttons, body text) — not the admin panel, which keeps its own
        consistent look regardless of theme.
    </p>
    <p>
        <label for="theme_accent_color">Accent color</label><br>
        <input type="color" id="theme_accent_color" name="theme_accent_color" value="<?= e($accentColor) ?>">
    </p>
    <p>
        <label for="theme_font_stack">Body font</label><br>
        <select id="theme_font_stack" name="theme_font_stack">
            <?php foreach ($fontStackOptions as $key => $option): ?>
                <option value="<?= e($key) ?>" <?= $fontStack === $key ? 'selected' : '' ?> style="font-family: <?= e($option['css']) ?>;"><?= e($option['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="theme_dark_mode">Dark mode</label><br>
        <select id="theme_dark_mode" name="theme_dark_mode">
            <option value="off" <?= $darkMode === 'off' ? 'selected' : '' ?>>Off (always light)</option>
            <option value="on" <?= $darkMode === 'on' ? 'selected' : '' ?>>On (always dark)</option>
            <option value="auto" <?= $darkMode === 'auto' ? 'selected' : '' ?>>Auto — follows each visitor's device, with a manual toggle</option>
        </select><br>
        <small>"Auto" adds a small toggle button to the header so a visitor can override their device's preference; it's remembered on their next visit.</small>
    </p>

    <h2>Language</h2>
    <p style="color:#666;">
        Applies site-wide, to every visitor and the admin panel alike —
        there's no per-member language preference. Only pages that have
        actually been translated change; anything untranslated falls
        back to English rather than showing a raw key or blank text.
    </p>
    <p>
        <label for="site_language">Site language</label><br>
        <select id="site_language" name="site_language">
            <?php foreach ($availableLanguages as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $siteLanguage === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </p>

    <h2>Maintenance mode</h2>
    <p style="color:#666;">
        While on, every visitor except signed-in staff sees a simple
        "down for maintenance" page instead of the site. You can still
        log in and use the admin panel as normal.
    </p>

    <p>
        <label><input type="checkbox" name="maintenance_mode" value="1" <?= $maintenanceMode ? 'checked' : '' ?>> Enable maintenance mode</label>
    </p>

    <p>
        <label for="maintenance_message">Message shown to visitors</label><br>
        <textarea id="maintenance_message" name="maintenance_message" rows="3" cols="60" placeholder="We're performing scheduled maintenance. Please check back soon."><?= e($maintenanceMessage) ?></textarea>
    </p>

    <button type="submit">Save</button>
</form>

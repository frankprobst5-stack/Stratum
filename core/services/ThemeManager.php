<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Discovers installed themes (built-in + custom/uploaded) and manages
 * which one is active — the theme equivalent of ModuleManager, though
 * simpler: there's no enable/disable/dependency graph, just "which one
 * theme.json-bearing directory is currently active," stored as a single
 * `active_theme` row in core_settings (read once at boot by
 * public/index.php to construct TemplateEngine, same as every other
 * settings-row read in this app).
 */
final class ThemeManager
{
    public function __construct(
        private readonly Database $db,
        private readonly string $themesDir,
        private readonly ?string $customThemesDir = null
    ) {
    }

    /** @return array<int, array{id: string, name: string, version: string, description: string, custom: bool, active: bool}> */
    public function list(): array
    {
        $active = $this->activeThemeId();
        $themes = [];

        foreach ($this->discoverFrom($this->themesDir) as $id => $meta) {
            $themes[$id] = $meta + ['custom' => false];
        }

        if ($this->customThemesDir !== null) {
            foreach ($this->discoverFrom($this->customThemesDir) as $id => $meta) {
                // A built-in theme of the same id always wins — mirrors
                // ModuleManager::discoverFrom()'s same "first found wins"
                // defense-in-depth, even though the installers already
                // refuse this collision at upload time.
                if (!isset($themes[$id])) {
                    $themes[$id] = $meta + ['custom' => true];
                }
            }
        }

        return array_values(array_map(
            static fn (array $t): array => $t + ['active' => $t['id'] === $active],
            $themes
        ));
    }

    public function activeThemeId(): string
    {
        $row = $this->db->fetchOne(
            'SELECT `value` FROM ' . $this->db->table('core_settings') . " WHERE `key` = 'active_theme'"
        );

        return $row['value'] ?? 'default';
    }

    /** True if $id names a real, installed theme and the setting was updated. */
    public function setActive(string $id): bool
    {
        $exists = array_filter($this->list(), static fn (array $t): bool => $t['id'] === $id);
        if ($exists === []) {
            return false;
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('core_settings') . " WHERE `key` = 'active_theme'"
        );
        $now = date('Y-m-d H:i:s');

        if ($existing === null) {
            $this->db->insert('core_settings', [
                'key' => 'active_theme',
                'value' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->execute(
                'UPDATE ' . $this->db->table('core_settings') . " SET `value` = :value, updated_at = :now WHERE `key` = 'active_theme'",
                ['value' => $id, 'now' => $now]
            );
        }

        return true;
    }

    public function isCustom(string $id): bool
    {
        foreach ($this->list() as $theme) {
            if ($theme['id'] === $id) {
                return $theme['custom'];
            }
        }

        return false;
    }

    /** @return array<string, array{id: string, name: string, version: string, description: string}> */
    private function discoverFrom(string $dir): array
    {
        $themes = [];

        foreach (glob("{$dir}/*/theme.json") ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest) || !isset($manifest['id']) || !is_string($manifest['id'])) {
                continue;
            }

            $themes[$manifest['id']] = [
                'id' => $manifest['id'],
                'name' => $manifest['name'] ?? $manifest['id'],
                'version' => $manifest['version'] ?? '',
                'description' => $manifest['description'] ?? '',
            ];
        }

        return $themes;
    }
}

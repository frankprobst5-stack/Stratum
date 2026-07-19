<?php

declare(strict_types=1);

namespace Stratum\Core;

final class ModuleManager
{
    private const NON_DISABLEABLE = ['users'];

    /** @var array<string, array{path: string, manifest: array<string, mixed>, custom: bool}> */
    private array $modules = [];

    /**
     * $customModulesDir is optional and may not exist yet (no addon ever
     * uploaded) — discover() below tolerates a missing directory the same
     * way it always tolerated $modulesDir being empty. Uploaded addons
     * (AddonPackageInstaller) live there; everything about how they're
     * booted, enabled, or capability-synced afterward is identical to a
     * built-in module — this class doesn't know or care which directory
     * a given module actually came from except for the 'custom' flag,
     * which exists purely so the admin UI can decide what's safe to
     * offer a delete button for.
     */
    public function __construct(
        private readonly Database $db,
        private readonly string $modulesDir,
        private readonly ?string $customModulesDir = null
    ) {
        $this->discover();
        $this->syncState();
        $this->syncCapabilities();
    }

    private function discover(): void
    {
        $this->discoverFrom($this->modulesDir, custom: false);

        if ($this->customModulesDir !== null) {
            $this->discoverFrom($this->customModulesDir, custom: true);
        }
    }

    private function discoverFrom(string $dir, bool $custom): void
    {
        foreach (glob("{$dir}/*/module.json") ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest) || !isset($manifest['id'])) {
                continue;
            }

            // A custom addon can never shadow an existing (built-in or
            // already-discovered custom) module id — first one found wins,
            // matching AddonPackageInstaller's own upload-time uniqueness
            // check; this is just defense in depth if a directory was ever
            // hand-edited outside that installer.
            if (isset($this->modules[$manifest['id']])) {
                continue;
            }

            $this->modules[$manifest['id']] = [
                'path' => dirname($manifestPath),
                'manifest' => $manifest,
                'custom' => $custom,
            ];
        }
    }

    private function syncState(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (array_keys($this->modules) as $id) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM ' . $this->db->table('core_modules') . ' WHERE module_id = :id',
                ['id' => $id]
            );

            if ($existing === null) {
                $this->db->insert('core_modules', [
                    'module_id' => $id,
                    'is_enabled' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Inserts strat_capabilities rows for any module-declared capability not
     * seen before, and grants it to admin/founder by default (revocable via
     * the permission matrix) — see docs/permission-model.md and the Stage 2
     * plan for why this can't happen at migration time. Silently does
     * nothing if the permission tables don't exist yet (e.g. mid-install,
     * before core migration 002 has run).
     */
    private function syncCapabilities(): void
    {
        $rolesTable = $this->db->table('roles');
        $capsTable = $this->db->table('capabilities');

        try {
            $adminRole = $this->db->fetchOne("SELECT id FROM {$rolesTable} WHERE name = 'admin'");
            $founderRole = $this->db->fetchOne("SELECT id FROM {$rolesTable} WHERE name = 'founder'");
        } catch (\Throwable) {
            return;
        }

        $grantRoleIds = array_filter([$adminRole['id'] ?? null, $founderRole['id'] ?? null]);
        $now = date('Y-m-d H:i:s');

        foreach ($this->modules as $id => $module) {
            foreach ($module['manifest']['provides_capabilities'] ?? [] as $key) {
                $existing = $this->db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = :key", ['key' => $key]);
                if ($existing !== null) {
                    continue;
                }

                $capabilityId = $this->db->insert('capabilities', [
                    'key' => $key,
                    'module_id' => $id,
                    'label' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($grantRoleIds as $roleId) {
                    $this->db->insert('role_capabilities', [
                        'role_id' => $roleId,
                        'capability_id' => $capabilityId,
                        'scope_type' => null,
                        'scope_id' => null,
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    /** @return array<string, bool> module_id => enabled */
    private function enabledState(): array
    {
        $rows = $this->db->fetchAll('SELECT module_id, is_enabled FROM ' . $this->db->table('core_modules'));

        $state = [];
        foreach ($rows as $row) {
            $state[$row['module_id']] = (bool) $row['is_enabled'];
        }

        return $state;
    }

    /** @return array<int, array{id: string, name: string, core: bool, enabled: bool, disableable: bool, custom: bool}> */
    public function list(): array
    {
        $state = $this->enabledState();
        $result = [];

        foreach ($this->modules as $id => $module) {
            $result[] = [
                'id' => $id,
                'name' => $module['manifest']['name'] ?? $id,
                'core' => (bool) ($module['manifest']['core'] ?? false),
                'enabled' => $state[$id] ?? true,
                'disableable' => !in_array($id, self::NON_DISABLEABLE, true),
                'custom' => $module['custom'],
            ];
        }

        return $result;
    }

    /**
     * The full dependency picture per module — what it requires (already
     * enforced at enable/disable time by assertDependenciesEnabled()/
     * assertNoEnabledDependents() below, this just visualizes the same
     * `requires` data those two already read) and, the direction nothing
     * currently surfaces anywhere, what requires *it*.
     *
     * @return array<int, array{id: string, name: string, enabled: bool,
     *     requires: array<int, array{id: string, name: string, enabled: bool}>,
     *     requiredBy: array<int, array{id: string, name: string, enabled: bool}>}>
     */
    public function dependencyGraph(): array
    {
        $state = $this->enabledState();
        $nameOf = fn (string $id): string => $this->modules[$id]['manifest']['name'] ?? $id;
        // A required id with no entry in $this->modules at all (an addon
        // declaring a `requires` that was never actually installed) is
        // worse than "disabled" — false here, not the "?? true" default
        // that would silently claim a nonexistent module is enabled.
        $isEnabled = fn (string $id): bool => isset($this->modules[$id]) && ($state[$id] ?? true);

        $graph = [];
        foreach ($this->modules as $id => $module) {
            $requires = $module['manifest']['requires'] ?? [];
            $requiredBy = [];

            foreach ($this->modules as $otherId => $otherModule) {
                if (in_array($id, $otherModule['manifest']['requires'] ?? [], true)) {
                    $requiredBy[] = ['id' => $otherId, 'name' => $nameOf($otherId), 'enabled' => $isEnabled($otherId)];
                }
            }

            $graph[] = [
                'id' => $id,
                'name' => $nameOf($id),
                'enabled' => $isEnabled($id),
                'requires' => array_map(
                    static fn (string $depId): array => ['id' => $depId, 'name' => $nameOf($depId), 'enabled' => $isEnabled($depId)],
                    $requires
                ),
                'requiredBy' => $requiredBy,
            ];
        }

        usort($graph, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $graph;
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        if (!$enabled && in_array($id, self::NON_DISABLEABLE, true)) {
            throw new \RuntimeException("The '{$id}' module cannot be disabled.");
        }

        if ($enabled) {
            $this->assertDependenciesEnabled($id);
        } else {
            $this->assertNoEnabledDependents($id);
        }

        $this->db->execute(
            'UPDATE ' . $this->db->table('core_modules') . ' SET is_enabled = :enabled, updated_at = :now WHERE module_id = :id',
            ['enabled' => $enabled ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function isCustom(string $id): bool
    {
        return $this->modules[$id]['custom'] ?? false;
    }

    /**
     * Deletes the DB-side registration for a custom (uploaded) module —
     * its `core_modules` row and any capabilities it declared (and their
     * grants). Deliberately refuses to run against a built-in module
     * (`custom` false) — this exists for AddonPackageInstaller::remove()
     * to pair with, never for disabling/tidying up a shipped module. The
     * caller is responsible for disabling the module first if it's
     * currently enabled (same `assertNoEnabledDependents()` check
     * setEnabled() already applies) and for the actual filesystem removal.
     */
    public function forgetCustomModule(string $id): void
    {
        if (!$this->isCustom($id)) {
            return;
        }

        $capsTable = $this->db->table('capabilities');
        $capabilityIds = $this->db->fetchAll("SELECT id FROM {$capsTable} WHERE module_id = :id", ['id' => $id]);

        foreach ($capabilityIds as $capability) {
            $this->db->execute(
                'DELETE FROM ' . $this->db->table('role_capabilities') . ' WHERE capability_id = :id',
                ['id' => $capability['id']]
            );
        }
        $this->db->execute("DELETE FROM {$capsTable} WHERE module_id = :id", ['id' => $id]);

        $this->db->execute(
            'DELETE FROM ' . $this->db->table('core_modules') . ' WHERE module_id = :id',
            ['id' => $id]
        );
    }

    /** Refuse to enable a module unless every module it `requires` is already enabled. */
    private function assertDependenciesEnabled(string $id): void
    {
        $requires = $this->modules[$id]['manifest']['requires'] ?? [];
        if ($requires === []) {
            return;
        }

        $state = $this->enabledState();
        foreach ($requires as $dependencyId) {
            if (!($state[$dependencyId] ?? false)) {
                throw new \RuntimeException("Cannot enable '{$id}': it requires '{$dependencyId}', which is not enabled.");
            }
        }
    }

    /** Refuse to disable a module that another currently-enabled module still requires. */
    private function assertNoEnabledDependents(string $id): void
    {
        foreach ($this->enabledModules() as $dependentId => $module) {
            if ($dependentId === $id) {
                continue;
            }

            if (in_array($id, $module['manifest']['requires'] ?? [], true)) {
                throw new \RuntimeException("Cannot disable '{$id}': '{$dependentId}' still requires it.");
            }
        }
    }

    public function isEnabled(string $moduleId): bool
    {
        return $this->enabledState()[$moduleId] ?? true;
    }

    /** @return array<string, array{path: string, manifest: array<string, mixed>}> */
    private function enabledModules(): array
    {
        $state = $this->enabledState();

        return array_filter(
            $this->modules,
            static fn (string $id): bool => $state[$id] ?? true,
            ARRAY_FILTER_USE_KEY
        );
    }

    /** @return array<int, array{label: string, route: string}> */
    public function navItems(): array
    {
        $nav = [];
        foreach ($this->enabledModules() as $module) {
            foreach ($module['manifest']['nav'] ?? [] as $item) {
                $nav[] = ['label' => $item['label'], 'route' => $item['route']];
            }
        }

        return $nav;
    }

    /** @return array<int, array{label: string, route: string}> nav items shown only to logged-out visitors */
    public function guestNavItems(): array
    {
        $nav = [];
        foreach ($this->enabledModules() as $module) {
            foreach ($module['manifest']['guest_nav'] ?? [] as $item) {
                $nav[] = ['label' => $item['label'], 'route' => $item['route']];
            }
        }

        return $nav;
    }

    /** @return array<int, array{label: string, route: string, capability: string}> */
    public function adminNavItems(): array
    {
        $nav = [];
        foreach ($this->enabledModules() as $module) {
            foreach ($module['manifest']['admin_nav'] ?? [] as $item) {
                $nav[] = ['label' => $item['label'], 'route' => $item['route'], 'capability' => $item['capability']];
            }
        }

        return $nav;
    }

    /**
     * Requires each enabled module's services/, controllers/, Module.php, and
     * routes.php, in that order (so routes.php can reference classes the
     * earlier requires defined). Disabled modules are never touched.
     */
    public function boot(App $app): void
    {
        foreach ($this->enabledModules() as $module) {
            foreach (['services', 'controllers'] as $subDir) {
                foreach (glob($module['path'] . "/{$subDir}/*.php") ?: [] as $classFile) {
                    require_once $classFile;
                }
            }

            $moduleFile = $module['path'] . '/Module.php';
            if (is_file($moduleFile)) {
                $instance = require $moduleFile;
                if ($instance instanceof ModuleInterface) {
                    $instance->registerHooks($app->hooks);
                    $instance->registerBlocks($app->blocks);
                }
            }

            $routesFile = $module['path'] . '/routes.php';
            if (is_file($routesFile)) {
                (static function (string $__routesFile, App $app): void {
                    $router = $app->router;
                    require $__routesFile;
                })($routesFile, $app);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Admin-controllable overlay on top of `ModuleManager::navItems()`'s
 * purely dynamic, module-manifest-derived nav list — see the migration
 * 017 docblock for why this is a *reconciling* overlay (auto-adopts new
 * module nav items on read) rather than a one-time seed that would
 * silently stop tracking newly-enabled modules.
 */
final class NavMenuService
{
    private const PLACEMENTS = ['primary', 'more', 'hidden'];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * The read path every public page render actually uses. Reconciles
     * first (see syncModuleItems()), then returns weight-ordered,
     * non-hidden items split into the two buckets layout.php renders —
     * a module-sourced row whose module has been disabled *since* the
     * last sync is skipped here (not deleted — re-enabling the module
     * naturally makes it visible again with its prior customizations
     * intact, since the row was never removed).
     *
     * @param array<int, array{label: string, route: string}> $liveModuleNavItems
     * @return array{primary: array<int, array{id: int, label: string, route: string, external: bool}>, more: array<int, array{id: int, label: string, route: string, external: bool}>}
     */
    public function orderedItems(array $liveModuleNavItems): array
    {
        $this->syncModuleItems($liveModuleNavItems);

        $liveRoutes = array_column($liveModuleNavItems, 'route');
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('nav_menu_items') . " WHERE placement != 'hidden' ORDER BY weight ASC"
        );

        $result = ['primary' => [], 'more' => []];
        foreach ($rows as $row) {
            if ($row['source'] === 'module' && !in_array($row['route'], $liveRoutes, true)) {
                continue;
            }

            $bucket = $row['placement'] === 'primary' ? 'primary' : 'more';
            $result[$bucket][] = [
                'id' => (int) $row['id'],
                'label' => $row['label'],
                'route' => $row['route'],
                'external' => $this->isExternal($row['route']),
            ];
        }

        return $result;
    }

    /**
     * Everything, including hidden rows and stale module rows (a
     * module-sourced item whose module isn't currently live — flagged so
     * the admin UI can explain it rather than silently showing a link
     * that 404s), for the `/admin/menu` table.
     *
     * @param array<int, array{label: string, route: string}> $liveModuleNavItems
     * @return array<int, array<string, mixed>>
     */
    public function adminList(array $liveModuleNavItems): array
    {
        $this->syncModuleItems($liveModuleNavItems);

        $liveRoutes = array_column($liveModuleNavItems, 'route');
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('nav_menu_items') . ' ORDER BY placement, weight ASC'
        );

        foreach ($rows as &$row) {
            $row['is_stale'] = $row['source'] === 'module' && !in_array($row['route'], $liveRoutes, true);
        }

        return $rows;
    }

    /** New admin-added link — internal (e.g. "/pages/about") or external ("https://..."). Returns the new row's id, or false if label/route was blank. */
    public function createCustom(string $label, string $route): int|false
    {
        $label = trim($label);
        $route = trim($route);
        if ($label === '' || $route === '') {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('nav_menu_items', [
            'source' => 'custom',
            'route' => $route,
            'label' => $label,
            'placement' => 'more',
            'weight' => $this->nextWeightIn('more'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Label + placement are the two things the admin form actually edits
     * per row (route isn't editable for module items — it has to match
     * the module's real route to stay linked; a custom link's route can
     * be changed by deleting and re-adding, a deliberately narrow v1).
     * Changing placement re-appends the item to the end of its new
     * bucket rather than keeping its old numeric weight, so it lands
     * somewhere sensible instead of interleaving arbitrarily with the
     * bucket it's moving into.
     */
    public function updateItem(int $id, string $label, string $placement): void
    {
        if (!in_array($placement, self::PLACEMENTS, true)) {
            return;
        }

        $row = $this->find($id);
        if ($row === null) {
            return;
        }

        $weight = $row['placement'] === $placement ? (int) $row['weight'] : $this->nextWeightIn($placement);

        $this->db->execute(
            'UPDATE ' . $this->db->table('nav_menu_items') . ' SET label = :label, placement = :placement, weight = :weight, updated_at = :now WHERE id = :id',
            ['label' => trim($label) !== '' ? trim($label) : $row['label'], 'placement' => $placement, 'weight' => $weight, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function moveUp(int $id): void
    {
        $this->swapWithNeighbor($id, direction: -1);
    }

    public function moveDown(int $id): void
    {
        $this->swapWithNeighbor($id, direction: 1);
    }

    /**
     * For a `source = 'module'` row this resets that item back to
     * default placement/weight (syncModuleItems() re-adds it on the very
     * next read, since the row is genuinely gone) — a real, if implicit,
     * "reset to default" action. For a `source = 'custom'` row it's a
     * real, permanent delete.
     */
    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM ' . $this->db->table('nav_menu_items') . ' WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    private function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM ' . $this->db->table('nav_menu_items') . ' WHERE id = :id', ['id' => $id]);
    }

    /**
     * Inserts a default row (placement 'more', appended to the end) for
     * any live module nav item that doesn't have one yet — the only
     * write this class does without an explicit admin action, and the
     * reason a newly-enabled module's nav item still "just appears"
     * exactly like it did before this feature existed.
     *
     * @param array<int, array{label: string, route: string}> $liveModuleNavItems
     */
    private function syncModuleItems(array $liveModuleNavItems): void
    {
        $existingRoutes = array_column(
            $this->db->fetchAll('SELECT route FROM ' . $this->db->table('nav_menu_items') . " WHERE source = 'module'"),
            'route'
        );

        $missing = array_filter(
            $liveModuleNavItems,
            static fn (array $item): bool => !in_array($item['route'], $existingRoutes, true)
        );
        if ($missing === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $weight = $this->nextWeightIn('more');
        foreach ($missing as $item) {
            $this->db->insert('nav_menu_items', [
                'source' => 'module', 'route' => $item['route'], 'label' => $item['label'],
                'placement' => 'more', 'weight' => $weight,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $weight += 10;
        }
    }

    private function nextWeightIn(string $placement): int
    {
        $row = $this->db->fetchOne(
            'SELECT MAX(weight) AS max_weight FROM ' . $this->db->table('nav_menu_items') . ' WHERE placement = :placement',
            ['placement' => $placement]
        );

        return ((int) ($row['max_weight'] ?? 0)) + 10;
    }

    private function swapWithNeighbor(int $id, int $direction): void
    {
        $row = $this->find($id);
        if ($row === null) {
            return;
        }

        $table = $this->db->table('nav_menu_items');
        $comparator = $direction < 0 ? '<' : '>';
        $order = $direction < 0 ? 'DESC' : 'ASC';

        $neighbor = $this->db->fetchOne(
            "SELECT * FROM {$table} WHERE placement = :placement AND weight {$comparator} :weight ORDER BY weight {$order} LIMIT 1",
            ['placement' => $row['placement'], 'weight' => $row['weight']]
        );
        if ($neighbor === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->execute("UPDATE {$table} SET weight = :weight, updated_at = :now WHERE id = :id", ['weight' => $neighbor['weight'], 'now' => $now, 'id' => $row['id']]);
        $this->db->execute("UPDATE {$table} SET weight = :weight, updated_at = :now WHERE id = :id", ['weight' => $row['weight'], 'now' => $now, 'id' => $neighbor['id']]);
    }

    private function isExternal(string $route): bool
    {
        return str_starts_with($route, 'http://') || str_starts_with($route, 'https://');
    }
}

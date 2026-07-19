<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Admin CRUD over `block_placements`/`block_regions` — the piece that
 * was missing since `BlockRegistry` was first built (every block placed
 * so far, ads/sponsors/ticker/search/presence/the whole Stage 8 front-
 * page library, was migration-seeded only). Reordering within a region
 * is move-up/move-down against the existing `weight` column rather than
 * true drag-and-drop JS — same ordering mechanism forum boards/link
 * categories/etc. already use, and matches this app's "no framework,
 * minimal JS" posture instead of adding drag-event handling + an AJAX
 * persistence layer for a cosmetic difference in how the reorder is
 * triggered.
 */
final class BlockPlacementService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array{id: int, key: string, label: string}> */
    public function listRegions(): array
    {
        return $this->db->fetchAll(
            'SELECT id, `key`, label FROM ' . $this->db->table('block_regions') . ' ORDER BY `key`'
        );
    }

    /**
     * All placements, joined with their region's key/label, grouped by
     * region and weight-ordered within each — the shape the admin
     * template actually wants to render (one section per region).
     *
     * @return array<string, array<int, array<string, mixed>>> region key => placements
     */
    public function listGroupedByRegion(): array
    {
        $regionsTable = $this->db->table('block_regions');
        $placementsTable = $this->db->table('block_placements');

        $rows = $this->db->fetchAll(
            "SELECT p.*, r.`key` AS region_key, r.label AS region_label
             FROM {$placementsTable} p
             JOIN {$regionsTable} r ON r.id = p.region_id
             ORDER BY r.`key`, p.weight"
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['region_key']][] = $row;
        }

        return $grouped;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('block_placements') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** The new placement's id on success; false if $configJson is non-empty and not valid JSON. */
    public function create(string $blockType, int $regionId, string $pageScope, int $weight, string $configJson): int|false
    {
        $normalizedConfig = $this->normalizeConfig($configJson);
        if ($normalizedConfig === false) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('block_placements', [
            'block_type' => $blockType,
            'region_id' => $regionId,
            'page_scope' => $pageScope !== '' ? $pageScope : 'site_wide',
            'weight' => $weight,
            'config_json' => $normalizedConfig,
            'is_enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updatePageScope(int $id, string $pageScope): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('block_placements') . ' SET page_scope = :page_scope, updated_at = :now WHERE id = :id',
            ['page_scope' => $pageScope, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /**
     * Saves just a placement's config_json — the counterpart to the
     * `ConfigurableBlock`/`BlockConfigForm` settings-form flow
     * (`BlockPlacementsController::saveConfig()`), separate from
     * `create()` since a block's config is now filled in *after* it's
     * placed (drag it into a region first, then its settings form
     * appears), not both at once.
     */
    public function updateConfig(int $id, string $configJson): bool
    {
        $normalizedConfig = $this->normalizeConfig($configJson);
        if ($normalizedConfig === false) {
            return false;
        }

        $this->db->execute(
            'UPDATE ' . $this->db->table('block_placements') . ' SET config_json = :config_json, updated_at = :now WHERE id = :id',
            ['config_json' => $normalizedConfig, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );

        return true;
    }

    /**
     * Persists a drag-and-drop move — either reordering within one
     * region or dropping into a different one entirely, both expressed
     * the same way (this placement's new region_id + weight). $weight
     * is whatever the client computed for the drop position; no
     * server-side renumbering of siblings, same "exact values don't
     * matter, only relative order" reasoning `moveUp()`/`moveDown()`
     * already document.
     */
    public function updateRegionAndWeight(int $id, int $regionId, int $weight): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('block_placements') . ' SET region_id = :region_id, weight = :weight, updated_at = :now WHERE id = :id',
            ['region_id' => $regionId, 'weight' => $weight, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('block_placements') . ' SET is_enabled = :v, updated_at = :now WHERE id = :id',
            ['v' => $enabled ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM ' . $this->db->table('block_placements') . ' WHERE id = :id', ['id' => $id]);
    }

    /**
     * Swaps this placement's weight with the next-lower-weight placement
     * in the same region — a no-op if it's already first. Simple pairwise
     * swap rather than a full renumber: cheap, and the exact weight
     * values were never meant to be meaningful outside their relative
     * order anyway (same as every other `weight` column in this app).
     */
    public function moveUp(int $id): void
    {
        $this->swapWithNeighbor($id, direction: -1);
    }

    public function moveDown(int $id): void
    {
        $this->swapWithNeighbor($id, direction: 1);
    }

    private function swapWithNeighbor(int $id, int $direction): void
    {
        $placement = $this->find($id);
        if ($placement === null) {
            return;
        }

        $table = $this->db->table('block_placements');
        $comparator = $direction < 0 ? '<' : '>';
        $order = $direction < 0 ? 'DESC' : 'ASC';

        $neighbor = $this->db->fetchOne(
            "SELECT * FROM {$table}
             WHERE region_id = :region_id AND weight {$comparator} :weight
             ORDER BY weight {$order} LIMIT 1",
            ['region_id' => $placement['region_id'], 'weight' => $placement['weight']]
        );

        if ($neighbor === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            "UPDATE {$table} SET weight = :weight, updated_at = :now WHERE id = :id",
            ['weight' => $neighbor['weight'], 'now' => $now, 'id' => $placement['id']]
        );
        $this->db->execute(
            "UPDATE {$table} SET weight = :weight, updated_at = :now WHERE id = :id",
            ['weight' => $placement['weight'], 'now' => $now, 'id' => $neighbor['id']]
        );
    }

    /** @return string|false|null null if $configJson is blank (stored as NULL), the normalized JSON string on success, false if invalid */
    private function normalizeConfig(string $configJson): string|false|null
    {
        $trimmed = trim($configJson);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return false;
        }

        return json_encode($decoded, JSON_UNESCAPED_SLASHES);
    }
}

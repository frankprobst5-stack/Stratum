<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Core migration 002 seeded exactly one rank ("New Member", 0 points) —
 * enough to give every new user a default, but with only one rank ever
 * existing, `ranks.min_points` had nothing to actually promote anyone
 * *into*. A real reputation system (see `ReputationService`) needs a
 * real ladder to climb; thresholds are a reasonable, deliberately
 * simple first pass, not tuned against real club activity data yet.
 */
return new class implements Migration {
    private const RANKS = [
        ['name' => 'Active Member', 'min_points' => 25],
        ['name' => 'Veteran', 'min_points' => 100],
        ['name' => 'Community Pillar', 'min_points' => 300],
    ];

    public function up(Database $db): void
    {
        $now = date('Y-m-d H:i:s');
        $table = $db->table('ranks');

        foreach (self::RANKS as $rank) {
            $existing = $db->fetchOne("SELECT id FROM {$table} WHERE name = :name", ['name' => $rank['name']]);
            if ($existing !== null) {
                continue;
            }

            $db->insert('ranks', [
                'name' => $rank['name'],
                'min_points' => $rank['min_points'],
                'icon' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(Database $db): void
    {
        $table = $db->table('ranks');
        foreach (self::RANKS as $rank) {
            $db->execute("DELETE FROM {$table} WHERE name = :name", ['name' => $rank['name']]);
        }
    }
};

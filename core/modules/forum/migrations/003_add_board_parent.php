<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Sub-boards (nested board hierarchy) — Stage 3b shipped flat-only, tracked
 * since as an open Forum parity item. A single nullable self-referencing
 * `parent_id` supports arbitrary nesting depth for free (no separate
 * "level" column to keep in sync); the public/admin UI only ever renders
 * what an admin actually builds, so depth is a presentation concern, not a
 * schema one. `ON DELETE CASCADE` matches every other board deletion this
 * schema already cascades (category -> board); deleting a parent board
 * takes its sub-boards with it rather than orphaning them.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            ALTER TABLE ' . $db->table('forum_boards') . '
            ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER category_id,
            ADD KEY idx_forum_boards_parent (parent_id),
            ADD CONSTRAINT fk_forum_boards_parent FOREIGN KEY (parent_id)
                REFERENCES ' . $db->table('forum_boards') . ' (id) ON DELETE CASCADE
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE ' . $db->table('forum_boards') . ' DROP FOREIGN KEY fk_forum_boards_parent');
        $db->execute('ALTER TABLE ' . $db->table('forum_boards') . ' DROP COLUMN parent_id');
    }
};

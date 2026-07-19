<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(
            'ALTER TABLE ' . $db->table('articles') . '
             ADD COLUMN featured_image_url VARCHAR(500) NULL AFTER excerpt'
        );
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE ' . $db->table('articles') . ' DROP COLUMN featured_image_url');
    }
};

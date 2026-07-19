<?php

declare(strict_types=1);

namespace Stratum\Core;

interface Migration
{
    public function up(Database $db): void;

    public function down(Database $db): void;
}

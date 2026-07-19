<?php

declare(strict_types=1);

namespace Stratum\Modules\MyAddon;

use Stratum\Core\Database;

/**
 * Example service — this is where DB access/business logic lives in
 * every module in this app; controllers stay thin and call into a
 * service like this one. Delete this file if your addon doesn't need
 * its own database table.
 */
final class MyAddonService
{
    public function __construct(private readonly Database $db)
    {
    }
}

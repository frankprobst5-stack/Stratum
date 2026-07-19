<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Dues\DuesService;

/**
 * Takes a constructor argument (same pattern as rss_aggregator's
 * Module.php) — registerHooks()'s cron.daily listener needs
 * $app->db/$app->permissions, and $app is in scope at the
 * `require $moduleFile` call site in ModuleManager::boot().
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'dues';
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }

    public function registerHooks(HookRegistry $hooks): void
    {
        $hooks->listen('cron.daily', function (): void {
            (new DuesService($this->app->db, $this->app->permissions))->revokeExpiredPremiumMemberships();
        });
    }

    public function registerBlocks(BlockRegistry $blocks): void
    {
    }
};

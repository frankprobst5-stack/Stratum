<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Ads\AdBlock;
use Stratum\Modules\Ads\AdService;

/**
 * Takes a constructor argument (same pattern as ticker/dues' Module.php) —
 * registerBlocks() needs $app->db to build the AdBlock factory, and $app is
 * in scope at the `require $moduleFile` call site in ModuleManager::boot().
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'ads';
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }

    public function registerHooks(HookRegistry $hooks): void
    {
    }

    public function registerBlocks(BlockRegistry $blocks): void
    {
        $blocks->register('ads.banner', fn (): AdBlock => new AdBlock(new AdService($this->app->db)));
    }
};

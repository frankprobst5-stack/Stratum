<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Sponsors\SponsorBlock;
use Stratum\Modules\Sponsors\SponsorService;

/**
 * Takes a constructor argument (same pattern as ads/ticker/dues'
 * Module.php) — registerBlocks() needs $app->db to build the
 * SponsorBlock factory, and $app is in scope at the `require
 * $moduleFile` call site in ModuleManager::boot().
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'sponsors';
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
        $blocks->register('sponsors.strip', fn (): SponsorBlock => new SponsorBlock(new SponsorService($this->app->db)));
    }
};

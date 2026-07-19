<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\OrgSpaces\FeaturedClubBlock;
use Stratum\Modules\OrgSpaces\OrgSpaceService;

/**
 * Takes a constructor argument (same pattern as ads/sponsors/ticker's
 * Module.php) — registerBlocks() needs $app->db to build the
 * FeaturedClubBlock factory.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'org_spaces';
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
        $blocks->register('org_spaces.featured_club', fn (): FeaturedClubBlock => new FeaturedClubBlock(
            new OrgSpaceService($this->app->db, $this->app->permissions)
        ));
    }
};

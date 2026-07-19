<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Activity\ActivityFeedBlock;
use Stratum\Modules\Activity\ActivityService;
use Stratum\Modules\Users\AuthService;

/**
 * Takes a constructor argument (same pattern as ads/sponsors/ticker's
 * Module.php) — registerBlocks() needs $app->db/$app->modules to build
 * the ActivityFeedBlock factory.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'activity';
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
        $blocks->register('activity.feed', fn (): ActivityFeedBlock => new ActivityFeedBlock(
            new ActivityService($this->app->db, $this->app->modules),
            new AuthService($this->app->db)
        ));
    }
};

<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Video\RecentVideosBlock;
use Stratum\Modules\Video\VideoService;

/**
 * Takes a constructor argument (same pattern as ads/sponsors/ticker's
 * Module.php) — registerBlocks() needs $app->db to build the
 * RecentVideosBlock factory. VideoService's storageDir argument (only
 * used by upload-path methods) is passed as an empty string — safe,
 * since a read-only block never calls those methods, same reasoning
 * gallery's Module.php uses for GalleryService.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'video';
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
        $blocks->register('video.recent', fn (): RecentVideosBlock => new RecentVideosBlock(new VideoService($this->app->db, '')));
    }
};

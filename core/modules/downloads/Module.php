<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Downloads\DownloadService;
use Stratum\Modules\Downloads\RecentDownloadsBlock;

/**
 * Takes a constructor argument (same pattern as ads/sponsors/ticker's
 * Module.php) — registerBlocks() needs $app->db to build the
 * RecentDownloadsBlock factory. DownloadService's storageDir argument
 * (only used by upload-path methods) is passed as an empty string —
 * safe, since a read-only block never calls those methods, same
 * reasoning gallery's/video's Module.php use for their own services.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'downloads';
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
        $blocks->register('downloads.recent', fn (): RecentDownloadsBlock => new RecentDownloadsBlock(new DownloadService($this->app->db, '')));
    }
};

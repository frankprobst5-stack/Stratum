<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Gallery\GalleryHighlightsBlock;
use Stratum\Modules\Gallery\GalleryService;

/**
 * Takes a constructor argument (same pattern as ads/sponsors/ticker's
 * Module.php) — registerBlocks() needs $app->db to build the
 * GalleryHighlightsBlock factory. The block never writes files, so the
 * GalleryService storageDir argument (only used by upload-path methods)
 * is passed as an empty string here — safe, since none of those methods
 * are ever called from a read-only block.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'gallery';
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
        $blocks->register('gallery.highlights', fn (): GalleryHighlightsBlock => new GalleryHighlightsBlock(
            new GalleryService($this->app->db, '')
        ));
    }
};

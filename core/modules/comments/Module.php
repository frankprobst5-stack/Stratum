<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\ContentResolver;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Comments\CommentService;
use Stratum\Modules\Comments\RecentCommentsBlock;
use Stratum\Modules\Users\AuthService;

/**
 * Takes a constructor argument (same pattern as ads/sponsors/ticker's
 * Module.php) — registerBlocks() needs $app->db to build the
 * RecentCommentsBlock factory.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'comments';
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
        $blocks->register('comments.recent', fn (): RecentCommentsBlock => new RecentCommentsBlock(
            new CommentService($this->app->db),
            new ContentResolver($this->app->db),
            new AuthService($this->app->db)
        ));
    }
};

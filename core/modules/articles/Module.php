<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Articles\ArticleService;
use Stratum\Modules\Articles\LatestContentBlock;

/**
 * Takes a constructor argument (same pattern as ticker's/rss_aggregator's
 * Module.php) — registerHooks()'s cron.daily listener needs $app->db.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'articles';
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
            (new ArticleService($this->app->db))->publishDueScheduled();
        });
    }

    public function registerBlocks(BlockRegistry $blocks): void
    {
        $blocks->register('articles.latest_content', fn (): LatestContentBlock => new LatestContentBlock(
            new ArticleService($this->app->db)
        ));
    }
};

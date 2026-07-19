<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\RssAggregator\RssFetcher;
use Stratum\Modules\RssAggregator\RssSourceService;

/**
 * Takes a constructor argument (same pattern as ticker's Module.php) —
 * registerHooks()'s cron.daily listener needs $app->db, and $app is in
 * scope at the `require $moduleFile` call site in ModuleManager::boot().
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'rss_aggregator';
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
            $sources = new RssSourceService($this->app->db);
            $fetcher = new RssFetcher($this->app->db);

            foreach ($sources->listSources() as $source) {
                if ((bool) $source['is_enabled']) {
                    $fetcher->fetchAndStore((int) $source['id']);
                }
            }
        });
    }

    public function registerBlocks(BlockRegistry $blocks): void
    {
    }
};

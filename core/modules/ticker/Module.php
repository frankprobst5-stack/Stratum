<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Ticker\TickerBlock;
use Stratum\Modules\Ticker\TickerService;

/**
 * Unlike every other module's Module.php, this one takes a constructor
 * argument — registerBlocks() needs $app->db to build the TickerBlock
 * factory, and $app is in scope at the `require $moduleFile` call site in
 * ModuleManager::boot(), so it can be passed straight into `new class($app)`.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'ticker';
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
        $blocks->register('ticker.messages', fn (): TickerBlock => new TickerBlock(new TickerService($this->app->db)));
    }
};

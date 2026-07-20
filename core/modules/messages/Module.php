<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Messages\MessagesIconBlock;
use Stratum\Modules\Messages\MessagesService;

/**
 * Takes a constructor argument (same pattern as ads/sponsors/ticker's
 * Module.php) — registerBlocks() needs $app->auth to build the
 * MessagesIconBlock factory.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'messages';
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
        $blocks->register('messages.icon', fn (): MessagesIconBlock => new MessagesIconBlock($this->app->auth, new MessagesService($this->app->db)));
    }
};

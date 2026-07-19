<?php

declare(strict_types=1);

use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Core\App;
use Stratum\Modules\Presence\PresenceService;
use Stratum\Modules\Presence\WhosOnlineBlock;

return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'presence';
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
        $blocks->register('presence.whosonline', fn (): WhosOnlineBlock => new WhosOnlineBlock(
            new PresenceService($this->app->db)
        ));
    }
};

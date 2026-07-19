<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Chat\AvailableRoomsBlock;
use Stratum\Modules\Chat\ChatService;

return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'chat';
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
        $blocks->register('chat.available_rooms', fn (): AvailableRoomsBlock => new AvailableRoomsBlock(new ChatService($this->app->db)));
    }
};

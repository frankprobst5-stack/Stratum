<?php

declare(strict_types=1);

use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;

/**
 * Throwaway proof module: the only thing it exists for is to be the concrete
 * thing Stage 1's success criterion tests against — toggle it off, its nav
 * entry and route disappear; toggle it back on, they return.
 */
return new class implements ModuleInterface {
    public function id(): string
    {
        return 'hello';
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
    }
};

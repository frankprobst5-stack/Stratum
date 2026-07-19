<?php

declare(strict_types=1);

use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;

/**
 * Starter addon — rename "my_addon" everywhere (module.json's "id", the
 * PHP namespace below, and the class names) before you upload this to a
 * real site. Everything here works as-is once uploaded, so you can
 * confirm your renamed copy still works before adding real functionality.
 */
return new class implements ModuleInterface {
    public function id(): string
    {
        return 'my_addon';
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

<?php

declare(strict_types=1);

namespace Stratum\Core;

interface ModuleInterface
{
    public function id(): string;

    /** Runs every time the module transitions off->on. Cheap/reversible state only — never schema changes. */
    public function onEnable(): void;

    /** Runs every time the module transitions on->off. */
    public function onDisable(): void;

    public function registerHooks(HookRegistry $hooks): void;

    public function registerBlocks(BlockRegistry $blocks): void;
}

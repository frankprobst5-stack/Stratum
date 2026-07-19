<?php

declare(strict_types=1);

use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Search\SearchBoxBlock;

return new class implements ModuleInterface {
    public function id(): string
    {
        return 'search';
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
        $blocks->register('search.searchbox', fn (): SearchBoxBlock => new SearchBoxBlock());
    }
};

<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Newsletter\CurrentIssueBlock;
use Stratum\Modules\Newsletter\NewsletterService;

/**
 * Takes a constructor argument (like ticker/Module.php) — registerBlocks()
 * needs $app->db to build the CurrentIssueBlock factory, and $app is in
 * scope at the `require $moduleFile` call site in ModuleManager::boot().
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'newsletter';
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
        $blocks->register('newsletter.current_issue', fn (): CurrentIssueBlock => new CurrentIssueBlock(new NewsletterService($this->app->db)));
    }
};

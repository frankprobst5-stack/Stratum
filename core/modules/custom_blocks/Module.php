<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BBCodeParser;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\CustomBlocks\HtmlBlock;
use Stratum\Modules\CustomBlocks\QuickLinksBlock;
use Stratum\Modules\CustomBlocks\TextBlock;
use Stratum\Modules\CustomBlocks\WelcomeCtaBlock;

/**
 * None of these four blocks need $app->db — they either read straight
 * from the placement's own config_json or (QuickLinksBlock, since the
 * 2026-07-19 design-system rebuild) pick from a small fixed internal
 * destination map. Still takes $app as a constructor arg for the same
 * consistent factory shape ads/sponsors/ticker's Module.php use, even
 * though nothing here currently reads from it.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'custom_blocks';
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
        $blocks->register('custom.html', fn (): HtmlBlock => new HtmlBlock());
        $blocks->register('custom.text', fn (): TextBlock => new TextBlock(new BBCodeParser()));
        $blocks->register('custom.welcome_cta', fn (): WelcomeCtaBlock => new WelcomeCtaBlock());
        $blocks->register('custom.quick_links', fn (): QuickLinksBlock => new QuickLinksBlock());
    }
};

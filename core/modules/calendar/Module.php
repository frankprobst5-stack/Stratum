<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Calendar\CalendarService;
use Stratum\Modules\Calendar\UpcomingEventsBlock;

/**
 * Takes a constructor argument (same pattern as ads/sponsors/ticker's
 * Module.php) — registerBlocks() needs $app->db to build the
 * UpcomingEventsBlock factory.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'calendar';
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
        $blocks->register('calendar.upcoming_events', fn (): UpcomingEventsBlock => new UpcomingEventsBlock(new CalendarService($this->app->db)));
    }
};

<?php

declare(strict_types=1);

use Stratum\Core\App;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\ModuleInterface;
use Stratum\Modules\Notifications\NotificationBellBlock;
use Stratum\Modules\Notifications\NotificationService;

/**
 * Takes a constructor argument like ticker's Module.php — both the hook
 * listener and the bell block factory need $app (db/auth), and $app is in
 * scope at the `require $moduleFile` call site in ModuleManager::boot().
 *
 * The 'notify' listener is the whole delivery mechanism: producers call
 * App::notify($event), which fires the hook. When this module is disabled,
 * this listener is never registered and every producer's notify() call is
 * a harmless no-op — no requires edges, no isEnabled() checks anywhere.
 */
return new class($app) implements ModuleInterface {
    public function __construct(private readonly App $app)
    {
    }

    public function id(): string
    {
        return 'notifications';
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }

    public function registerHooks(HookRegistry $hooks): void
    {
        $hooks->listen('notify', function (array $event): void {
            (new NotificationService($this->app->db))->push($event);
        });
    }

    public function registerBlocks(BlockRegistry $blocks): void
    {
        $blocks->register('notifications.bell', fn (): NotificationBellBlock => new NotificationBellBlock(
            $this->app->auth,
            new NotificationService($this->app->db)
        ));
    }
};

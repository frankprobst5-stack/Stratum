<?php

declare(strict_types=1);

namespace Stratum\Core;

final class HookRegistry
{
    /** @var array<string, callable[]> */
    private array $listeners = [];

    public function listen(string $hook, callable $listener): void
    {
        $this->listeners[$hook][] = $listener;
    }

    /**
     * Fires every listener for $hook, isolating failures — one listener
     * throwing (e.g. one RSS source timing out) must not stop the rest from
     * running. Returns the caught exceptions rather than logging them
     * itself, since HookRegistry has no Logger; callers with one (e.g.
     * bin/cron.php) log from the return value.
     *
     * @return \Throwable[]
     */
    public function fire(string $hook, mixed ...$args): array
    {
        $errors = [];

        foreach ($this->listeners[$hook] ?? [] as $listener) {
            try {
                $listener(...$args);
            } catch (\Throwable $e) {
                $errors[] = $e;
            }
        }

        return $errors;
    }
}

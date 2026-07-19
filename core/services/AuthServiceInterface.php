<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Implemented by the `users` module's AuthService. Core depends on this
 * abstraction rather than the module class directly, since modules are
 * `require`d dynamically, not autoloaded (see docs/module-interface.md).
 */
interface AuthServiceInterface
{
    /** @return array<string, mixed>|null */
    public function findByCredentials(string $login, string $password): ?array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;
}

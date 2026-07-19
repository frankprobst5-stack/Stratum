<?php

declare(strict_types=1);

namespace Stratum\Core;

interface Block
{
    /** @param array<string, mixed> $config */
    public function render(array $config): string;
}

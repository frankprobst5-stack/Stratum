<?php

declare(strict_types=1);

namespace Stratum\Core;

/** Thrown by UpdatePackageVerifier/UpdateApplier — the message is always safe to show an admin directly. */
final class UpdatePackageException extends \RuntimeException
{
}

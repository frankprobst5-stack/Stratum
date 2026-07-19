<?php

declare(strict_types=1);

namespace Stratum\Core;

/** Thrown by SafeZipExtractor/AddonPackageInstaller/ThemePackageInstaller — the message is always safe to show an admin directly. */
final class PackageInstallException extends \RuntimeException
{
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Dist;

use RuntimeException;

final class CouldNotReadRevision extends RuntimeException
{
    public static function manifest(string $revision): self
    {
        return new self("Could not read composer.json at {$revision}.");
    }

    public static function archive(string $revision): self
    {
        return new self("Could not build the archive listing for {$revision}.");
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Environment\Exceptions;

use RuntimeException;

final class EnvFileNotWritable extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self(sprintf(
            "Laravel .env file is not writable: %s\n" .
            "Hint: run `chmod u+w '%s'` (or check ownership).",
            $path,
            $path,
        ));
    }
}

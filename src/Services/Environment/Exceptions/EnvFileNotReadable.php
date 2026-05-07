<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Environment\Exceptions;

use RuntimeException;

final class EnvFileNotReadable extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self(sprintf(
            "Laravel .env file is not readable: %s\n" .
            "Hint: run `chmod u+r '%s'` (or check ownership).",
            $path,
            $path,
        ));
    }
}

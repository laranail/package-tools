<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Environment\Exceptions;

use RuntimeException;

final class EnvFileNotFound extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self(sprintf(
            "Laravel .env file not found at: %s\n" .
            'Hint: copy .env.example to .env (or set APP_ENV_PATH in your bootstrap).',
            $path,
        ));
    }
}

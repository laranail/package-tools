<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Wraps a failure in a package's boot-time WIRING — a `useHttps` / `setLocale`
 * closure, a route-model resolver, an event-subscriber closure — with a
 * message that names exactly which builder blew up, so the crash points
 * straight at the culprit instead of a bare stack trace deep in a closure.
 *
 * These failures are deliberately NOT swallowed: unlike a config decorator or
 * a missing seeder file (which degrade to a safe, working state), broken boot
 * wiring degrades to a *silently* broken app — HTTP served when HTTPS was
 * required, the wrong locale, a missing route binding surfacing later as a
 * 404. Failing loud here is safer than booting into that state.
 */
final class PackageBootException extends RuntimeException
{
    public static function from(string $subject, Throwable $previous): self
    {
        return new self(
            "Package boot wiring failed [{$subject}]: {$previous->getMessage()}",
            (int) $previous->getCode(),
            $previous,
        );
    }
}

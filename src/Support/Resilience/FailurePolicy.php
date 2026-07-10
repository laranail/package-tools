<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Resilience;

use Closure;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;
use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Throwable;

/**
 * How a package handles failures in its own boot-time wiring, split by one
 * question: **does swallowing the failure leave a safe, working state?**
 *
 *  - {@see self::rethrowing()} — for wiring that does NOT degrade safely
 *    (a `useHttps` / `setLocale` closure, a route-model resolver, an
 *    event-subscriber closure). Swallowing these boots a *silently broken*
 *    app (insecure scheme, wrong locale, phantom 404s), which is worse than
 *    a crash. So the throwable is wrapped in a {@see PackageBootException}
 *    that names the culprit and **rethrown** — fail loud, everywhere, with a
 *    useful message.
 *
 *  - {@see self::swallow()} — for wiring that DOES degrade safely (a config
 *    decorator falls back to undecorated config; a malformed seeder file just
 *    means no seed data). The failure is logged and skipped so one package
 *    can't crash host boot, and the app keeps working correctly.
 *
 * There is deliberately no strict/lenient-by-environment mode: masking a real
 * misconfiguration in production is hiding it in the worst possible place.
 */
final class FailurePolicy
{
    /**
     * Run boot wiring that must not fail silently: on throw, wrap it in a
     * {@see PackageBootException} naming `$subject` and rethrow.
     *
     * @template T
     *
     * @param Closure(): T $work
     * @return T
     */
    public static function rethrowing(Closure $work, string $subject): mixed
    {
        try {
            return $work();
        } catch (Throwable $e) {
            if ($e instanceof PackageBootException) {
                throw $e;
            }

            throw PackageBootException::from($subject, $e);
        }
    }

    /**
     * Run degrade-safe boot wiring: on throw, log and skip (returning
     * `$default`) so one package can't crash host boot.
     *
     * @template T
     *
     * @param Closure(): T $work
     * @param array<string, mixed> $context
     * @param T|null $default
     * @return T|null
     */
    public static function swallow(Closure $work, string $label, ?PackageLogger $logger = null, array $context = [], mixed $default = null): mixed
    {
        try {
            return $work();
        } catch (Throwable $e) {
            self::log($e, $label, $logger, $context);

            return $default;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function log(Throwable $e, string $label, ?PackageLogger $logger, array $context): void
    {
        $context = [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            ...$context,
        ];

        try {
            if ($logger instanceof PackageLogger) {
                $logger->error($e->getMessage(), $label, $context);

                return;
            }

            Log::error($e->getMessage(), $context);
        } catch (Throwable) {
            // Logging must never itself throw.
        }
    }
}

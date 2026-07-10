<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Resilience;

use Closure;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Throwable;

/**
 * The package-wide failure policy for **package-author boot wiring** — config
 * decorators, runtime tweaks, route bindings, event subscribers, seeder
 * discovery, scheduling, and safe component/view-composer registration.
 *
 * One rule, applied everywhere: **strict in development, lenient in
 * production.** A misconfiguration or a throwing author closure is:
 *
 *  - always **logged** (to the package's own logfile when a {@see PackageLogger}
 *    is supplied, else the default channel);
 *  - **rethrown** when strict, so the author sees and fixes it before it
 *    reaches production;
 *  - **skipped** when lenient, so one package can never crash the whole host
 *    app at boot.
 *
 * Strictness resolves from `package-tools.resilience.strict`
 * (`PACKAGE_TOOLS_STRICT`): `true`/`false` force it; `null` (the default) means
 * **strict everywhere except production**. This deliberately keeps development
 * tight — catch every issue before prod — while keeping production resilient.
 *
 * Infrastructure that must never throw regardless (logging, the run tracker,
 * doctor checks, the action reporter, CLI commands) does NOT flow through this
 * policy — it stays unconditionally safe.
 */
final class FailurePolicy
{
    /**
     * Whether boot-wiring failures should rethrow. `resilience.strict` wins
     * when set to a bool; otherwise strict everywhere except production.
     */
    public static function isStrict(): bool
    {
        $configured = function_exists('config') ? config('package-tools.resilience.strict') : null;

        if (is_bool($configured)) {
            return $configured;
        }

        return ! function_exists('app') || ! app()->isProduction();
    }

    /**
     * Run `$work`; on throw, always log, then rethrow (strict) or return
     * `$default` (lenient). `$label` groups the log line (e.g. "Config",
     * "Routing"); `$context` adds structured detail.
     *
     * @template T
     *
     * @param Closure(): T $work
     * @param array<string, mixed> $context
     * @param T|null $default
     * @return T|null
     */
    public static function guard(Closure $work, string $label, ?PackageLogger $logger = null, array $context = [], mixed $default = null): mixed
    {
        try {
            return $work();
        } catch (Throwable $e) {
            self::report($e, $label, $logger, $context);

            if (self::isStrict()) {
                throw $e;
            }

            return $default;
        }
    }

    /**
     * Log a boot-wiring failure without deciding strictness (for call sites
     * that must run their own rethrow logic, e.g. schedule handling).
     *
     * @param array<string, mixed> $context
     */
    public static function report(Throwable $e, string $label, ?PackageLogger $logger = null, array $context = []): void
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

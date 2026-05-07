<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Throwable;

/**
 * HasConsoleWrapper - Console-only execution wrapper
 *
 * Provides a unified, error-safe way to execute console-only code with:
 * - Automatic environment checking
 * - Additional condition support (andWhen)
 * - Return expectation handling
 * - Automatic error reporting
 */
trait HasConsoleWrapper
{
    /**
     * Execute a callback only when running in console
     *
     * This method provides a safe wrapper for console-only operations with:
     * - Automatic console environment checking
     * - Additional conditional execution via andWhen
     * - Return value handling with expectReturn
     * - Automatic error reporting
     *
     * @param callable $callback The callback to execute
     * @param bool|callable $andWhen Additional condition to check (bool or callable that returns bool)
     * @param bool $expectReturn Whether to expect and return a value from callback
     * @param mixed $default Default value to return if conditions not met or on error
     * @return mixed Returns callback result if expectReturn=true, null otherwise
     *
     * @example Basic usage
     * ```php
     * $this->shouldRunInConsole(function() {
     *     $this->publishes([...], 'config');
     * });
     * ```
     * @example With additional condition
     * ```php
     * $this->shouldRunInConsole(
     *     callback: fn() => $this->publishes([...], 'config'),
     *     andWhen: fn() => config('app.debug') === true
     * );
     * ```
     * @example With return value
     * ```php
     * $result = $this->shouldRunInConsole(
     *     callback: fn() => $this->doSomething(),
     *     expectReturn: true,
     *     default: false
     * );
     * ```
     */
    protected function shouldRunInConsole(
        callable $callback,
        bool|callable $andWhen = true,
        bool $expectReturn = false,
        mixed $default = null
    ): mixed {
        // Must be in console (non-negotiable)
        if (! app()->runningInConsole()) {
            return $expectReturn ? $default : null;
        }

        // Check additional condition
        $shouldProceed = match (true) {
            is_callable($andWhen) => $andWhen(),
            is_bool($andWhen) => $andWhen,
            default => true
        };

        if (! $shouldProceed) {
            return $expectReturn ? $default : null;
        }

        // Execute callback with error handling
        try {
            $result = $callback();

            return $expectReturn ? $result : null;
        } catch (Throwable $e) {
            // Report error automatically
            report($e);

            // Return default if expecting return, null otherwise
            return $expectReturn ? $default : null;
        }
    }

    /**
     * Execute callback only in console AND in specific environment(s)
     *
     * Convenience method combining console check with environment check.
     *
     * @param callable $callback The callback to execute
     * @param string|array $environments Environment(s) to run in
     * @param bool $expectReturn Whether to expect return value
     * @param mixed $default Default value on failure
     *
     * @example Run only in local environment
     * ```php
     * $this->shouldRunInConsoleEnvironment(
     *     fn() => $this->loadFactories(),
     *     'local'
     * );
     * ```
     * @example Run in local and testing
     * ```php
     * $this->shouldRunInConsoleEnvironment(
     *     fn() => $this->loadFactories(),
     *     ['local', 'testing']
     * );
     * ```
     */
    protected function shouldRunInConsoleEnvironment(
        callable $callback,
        string|array $environments,
        bool $expectReturn = false,
        mixed $default = null
    ): mixed {
        return $this->shouldRunInConsole(
            callback: $callback,
            andWhen: fn () => app()->environment($environments),
            expectReturn: $expectReturn,
            default: $default
        );
    }

    /**
     * Execute callback only in console AND when condition is true
     *
     * Convenience method for common pattern of checking console + custom condition.
     *
     * @param callable $callback The callback to execute
     * @param bool|callable $condition Condition to check
     * @param bool $expectReturn Whether to expect return value
     * @param mixed $default Default value on failure
     *
     * @example Run only if feature enabled
     * ```php
     * $this->shouldRunInConsoleWhen(
     *     fn() => $this->registerFeature(),
     *     fn() => config('package.feature_enabled', false)
     * );
     * ```
     */
    protected function shouldRunInConsoleWhen(
        callable $callback,
        bool|callable $condition,
        bool $expectReturn = false,
        mixed $default = null
    ): mixed {
        return $this->shouldRunInConsole(
            callback: $callback,
            andWhen: $condition,
            expectReturn: $expectReturn,
            default: $default
        );
    }

    /**
     * Execute multiple callbacks in console
     *
     * Convenience method to execute multiple callbacks in sequence.
     * Stops on first error but doesn't throw exception.
     *
     * @param array<callable> $callbacks Array of callbacks to execute
     * @param bool|callable $andWhen Additional condition
     * @return bool True if all succeeded, false if any failed or conditions not met
     *
     * @example Execute multiple publishes
     * ```php
     * $this->shouldRunInConsoleMultiple([
     *     fn() => $this->publishes([...], 'config'),
     *     fn() => $this->publishes([...], 'views'),
     *     fn() => $this->publishes([...], 'assets'),
     * ]);
     * ```
     */
    protected function shouldRunInConsoleMultiple(
        array $callbacks,
        bool|callable $andWhen = true
    ): bool {
        if (! app()->runningInConsole()) {
            return false;
        }

        $shouldProceed = match (true) {
            is_callable($andWhen) => $andWhen(),
            is_bool($andWhen) => $andWhen,
            default => true
        };

        if (! $shouldProceed) {
            return false;
        }

        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                report($e);

                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Database;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Simtabi\Laranail\PackageTools\Events\SeederExecuted;
use Simtabi\Laranail\PackageTools\Events\SeederExecuting;
use Simtabi\Laranail\PackageTools\Events\SeederFailed;
use Simtabi\Laranail\PackageTools\Events\SeedingFinished;
use Simtabi\Laranail\PackageTools\Events\SeedingStarted;
use Simtabi\Laranail\PackageTools\Exceptions\SeederException;
use Simtabi\Laranail\PackageTools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\PackageTools\Support\ForeignKeyCheckGuard;
use Simtabi\Laranail\PackageTools\ValueObjects\SeederExecutionStats;
use Throwable;

/**
 * Executes the seeders held in a `SeederRegistry`, grouped by package
 * namespace, with optional FK-toggle, optional event emission, per-seeder
 * error logging, and optional tree-structured console output (when a
 * `SeederConsoleFormatter` is supplied).
 *
 * Returns a typed {@see SeederExecutionStats} value object.
 */
final readonly class SeederExecutor
{
    public function __construct(
        private Application $app,
        private ?ForeignKeyCheckGuard $fkGuard = null,
        private ?SeederConsoleFormatterInterface $formatter = null,
    ) {}

    /**
     * Execute every configuration registered in `$registry`.
     */
    public function run(SeederRegistry $registry): SeederExecutionStats
    {
        if ($registry->isEmpty()) {
            return SeederExecutionStats::empty();
        }

        [$bucketsByPackage, $disableFk, $fireEvents, $parameters] = $this->bucketise($registry);

        $this->formatter?->initializeSession();

        if ($fireEvents) {
            Event::dispatch(new SeedingStarted(array_keys($bucketsByPackage)));
        }

        $execute = fn (): SeederExecutionStats => $this->runBuckets($bucketsByPackage, $parameters, $fireEvents);

        $stats = $disableFk
            ? ($this->fkGuard ?? new ForeignKeyCheckGuard)->run($execute)
            : $execute();

        $this->formatter?->displaySummary();

        if ($fireEvents) {
            Event::dispatch(new SeedingFinished(
                array_keys($bucketsByPackage),
                $stats->success,
                $stats->failed,
            ));
        }

        return $stats;
    }

    /**
     * Project the registry into per-package buckets and merged options.
     *
     * @return array{0: array<string, list<class-string<Seeder>>>, 1: bool, 2: bool, 3: array<string, mixed>}
     */
    private function bucketise(SeederRegistry $registry): array
    {
        $buckets = [];
        $disableFk = false;
        $fireEvents = false;
        $parameters = [];

        foreach ($registry->all() as $config) {
            $package = $config['namespace'] ?? 'Default';
            $buckets[$package] = array_merge($buckets[$package] ?? [], $config['seeders']);

            $opts = $config['options'];
            $disableFk = $disableFk || ($opts['disable_foreign_key_checks'] ?? true);
            $fireEvents = $fireEvents || ($opts['fire_events'] ?? false);
            $parameters = array_merge($parameters, $opts['parameters'] ?? []);
        }

        return [$buckets, $disableFk, $fireEvents, $parameters];
    }

    /**
     * @param array<string, list<class-string<Seeder>>> $buckets
     * @param array<string, mixed> $parameters
     */
    private function runBuckets(array $buckets, array $parameters, bool $fireEvents): SeederExecutionStats
    {
        $success = 0;
        $failed = 0;
        $totalTime = 0.0;
        $errors = [];

        $packageNames = array_keys($buckets);
        $lastPackage = end($packageNames);

        foreach ($buckets as $package => $seederClasses) {
            $this->formatter?->displayGroupHeader((string) $package, count($seederClasses), $package === $lastPackage);

            $lastIndex = count($seederClasses) - 1;

            foreach ($seederClasses as $index => $class) {
                $isLast = $index === $lastIndex;
                $start = microtime(true);

                if ($fireEvents) {
                    Event::dispatch(new SeederExecuting($class, (string) $package));
                }
                $this->formatter?->displaySeederStart($class, $isLast);

                try {
                    $seeder = $this->resolveSeeder($class, $parameters);
                    // Invoke through `__invoke()` rather than `->run()`.
                    // Laravel's base `Seeder` dispatches via the invoke wrapper.
                    $seeder();

                    $durationMs = (microtime(true) - $start) * 1000;
                    $totalTime += $durationMs;
                    $success++;

                    if ($fireEvents) {
                        Event::dispatch(new SeederExecuted($class, $durationMs, (string) $package));
                    }
                    $this->formatter?->displaySeederSuccess($class, $durationMs / 1000, $isLast);
                } catch (Throwable $e) {
                    $durationMs = (microtime(true) - $start) * 1000;
                    $totalTime += $durationMs;
                    $failed++;
                    $errors[] = ['class' => $class, 'message' => $e->getMessage(), 'package' => (string) $package];

                    if ($fireEvents) {
                        Event::dispatch(new SeederFailed($class, $e, (string) $package));
                    }
                    $this->formatter?->displaySeederError($class, $this->toException($e), $durationMs / 1000, $isLast);

                    Log::error("Package seeder failed: {$class}", [
                        'package' => $package,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }
        }

        return new SeederExecutionStats(
            total: $success + $failed,
            success: $success,
            failed: $failed,
            totalTime: $totalTime,
            errors: $errors,
        );
    }

    /**
     * Resolve a seeder. Hand `$parameters` to the constructor when the
     * seeder accepts them; otherwise fall back to a parameterless `make()`.
     *
     * @param class-string<Seeder> $class
     * @param array<string, mixed> $parameters
     */
    private function resolveSeeder(string $class, array $parameters): Seeder
    {
        if (! class_exists($class)) {
            throw SeederException::classNotFound($class);
        }

        if (! is_subclass_of($class, Seeder::class)) {
            throw SeederException::invalidClass($class);
        }

        $reflection = new ReflectionClass($class);
        $ctor = $reflection->getConstructor();

        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            return $this->app->make($class);
        }

        return $this->app->make($class, $parameters);
    }

    /**
     * The console formatter's error display expects an \Exception; wrap any
     * Throwable that isn't already one.
     */
    private function toException(Throwable $e): Exception
    {
        return $e instanceof Exception ? $e : new Exception($e->getMessage(), $e->getCode(), $e);
    }
}

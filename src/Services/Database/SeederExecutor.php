<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Simtabi\Laranail\Package\Tools\Events\SeederExecuted;
use Simtabi\Laranail\Package\Tools\Events\SeederExecuting;
use Simtabi\Laranail\Package\Tools\Events\SeederFailed;
use Simtabi\Laranail\Package\Tools\Events\SeedingFinished;
use Simtabi\Laranail\Package\Tools\Events\SeedingStarted;
use Simtabi\Laranail\Package\Tools\Exceptions\SeederException;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\Package\Tools\Support\ForeignKeyCheckGuard;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;
use Throwable;

/**
 * Executes the bundles held in a `SeederRegistry` in priority order (lower
 * first, ties keep registration order), with per-bundle FK-toggle, event
 * emission, and constructor parameters — one package's options never leak
 * into another's run. Optional tree-structured console output when a
 * `SeederConsoleFormatter` is supplied.
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
     * Execute every bundle registered in `$registry`.
     */
    public function run(SeederRegistry $registry): SeederExecutionStats
    {
        if ($registry->isEmpty()) {
            return SeederExecutionStats::empty();
        }

        $bundles = $this->sortedBundles($registry);
        $anyFireEvents = array_any($bundles, static fn (SeederBundle $bundle): bool => $bundle->shouldFireEvents());
        $groups = array_values(array_map(
            static fn (SeederBundle $bundle): string => $bundle->namespace() ?? 'Default',
            $bundles,
        ));

        $this->formatter?->initializeSession();

        if ($anyFireEvents) {
            Event::dispatch(new SeedingStarted($groups));
        }

        $success = 0;
        $failed = 0;
        $totalTime = 0.0;
        $errors = [];

        $lastIndex = count($bundles) - 1;

        foreach ($bundles as $index => $bundle) {
            $execute = fn (): SeederExecutionStats => $this->runBundle($bundle, $index === $lastIndex);

            $stats = $bundle->disablesForeignKeyChecks()
                ? ($this->fkGuard ?? new ForeignKeyCheckGuard)->run($execute)
                : $execute();

            $success += $stats->success;
            $failed += $stats->failed;
            $totalTime += $stats->totalTime;
            $errors = [...$errors, ...$stats->errors];
        }

        $this->formatter?->displaySummary();

        if ($anyFireEvents) {
            Event::dispatch(new SeedingFinished($groups, $success, $failed));
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
     * Priority ascending; the sort is stable (php >= 8.0), so equal
     * priorities keep registration order.
     *
     * @return list<SeederBundle>
     */
    private function sortedBundles(SeederRegistry $registry): array
    {
        $bundles = array_values($registry->all());

        usort($bundles, static fn (SeederBundle $a, SeederBundle $b): int => $a->priorityValue() <=> $b->priorityValue());

        return $bundles;
    }

    private function runBundle(SeederBundle $bundle, bool $isLastBundle): SeederExecutionStats
    {
        $group = $bundle->namespace() ?? 'Default';
        $seederClasses = $bundle->seeders();
        $fireEvents = $bundle->shouldFireEvents();
        $parameters = $bundle->parametersValue();

        $this->formatter?->displayGroupHeader($group, count($seederClasses), $isLastBundle);

        $success = 0;
        $failed = 0;
        $totalTime = 0.0;
        $errors = [];

        $lastIndex = count($seederClasses) - 1;

        foreach ($seederClasses as $index => $class) {
            $isLast = $index === $lastIndex;
            $start = microtime(true);

            if ($fireEvents) {
                Event::dispatch(new SeederExecuting($class, $group));
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
                    Event::dispatch(new SeederExecuted($class, $durationMs, $group));
                }
                $this->formatter?->displaySeederSuccess($class, $durationMs / 1000, $isLast);
            } catch (Throwable $e) {
                $durationMs = (microtime(true) - $start) * 1000;
                $totalTime += $durationMs;
                $failed++;
                $errors[] = ['class' => $class, 'message' => $e->getMessage(), 'package' => $group];

                if ($fireEvents) {
                    Event::dispatch(new SeederFailed($class, $e, $group));
                }
                $this->formatter?->displaySeederError($class, $this->toException($e), $durationMs / 1000, $isLast);

                Log::error("Package seeder failed: {$class}", [
                    'package' => $group,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
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

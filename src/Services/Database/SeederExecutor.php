<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Database;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Simtabi\Laranail\PackageTools\Events\SeedingFinished;
use Simtabi\Laranail\PackageTools\Events\SeedingStarted;
use Simtabi\Laranail\PackageTools\Support\ForeignKeyCheckGuard;
use Throwable;

/**
 * Executes the seeders held in a `SeederRegistry`, grouped by package
 * namespace, with optional FK-toggle, optional event emission, and per-
 * seeder error logging.
 *
 * Stays narrow on purpose — output formatting / progress bars belong to
 * a separate concern (`Services\Utility\ProgressIndicator`) and are
 * driven by the calling Artisan command, not here.
 */
final readonly class SeederExecutor
{
    public function __construct(
        private Application $app,
        private ?ForeignKeyCheckGuard $fkGuard = null,
    ) {}

    /**
     * Execute every configuration registered in `$registry`.
     *
     * @return array{success: int, failed: int, executed: list<class-string<Seeder>>}
     */
    public function run(SeederRegistry $registry): array
    {
        if ($registry->isEmpty()) {
            return ['success' => 0, 'failed' => 0, 'executed' => []];
        }

        [$bucketsByPackage, $disableFk, $fireEvents, $parameters] = $this->bucketise($registry);

        if ($fireEvents) {
            Event::dispatch(new SeedingStarted(array_keys($bucketsByPackage)));
        }

        $execute = fn (): array => $this->runBuckets($bucketsByPackage, $parameters);

        $stats = $disableFk
            ? ($this->fkGuard ?? new ForeignKeyCheckGuard)->run($execute)
            : $execute();

        if ($fireEvents) {
            Event::dispatch(new SeedingFinished(
                array_keys($bucketsByPackage),
                $stats['success'],
                $stats['failed'],
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
     * @return array{success: int, failed: int, executed: list<class-string<Seeder>>}
     */
    private function runBuckets(array $buckets, array $parameters): array
    {
        $stats = ['success' => 0, 'failed' => 0, 'executed' => []];

        foreach ($buckets as $package => $seederClasses) {
            foreach ($seederClasses as $class) {
                try {
                    $seeder = $this->resolveSeeder($class, $parameters);
                    // Invoke through `__invoke()` rather than calling
                    // `->run()` directly — Laravel's base `Seeder` doesn't
                    // declare `run()` in its public surface; subclasses
                    // implement it and the framework dispatches via the
                    // magic invoke wrapper.
                    $seeder();
                    $stats['success']++;
                    $stats['executed'][] = $class;
                } catch (Throwable $e) {
                    $stats['failed']++;
                    Log::error("Package seeder failed: {$class}", [
                        'package' => $package,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }
        }

        return $stats;
    }

    /**
     * Resolve a seeder. Hand `$parameters` to the constructor when the
     * seeder accepts them (e.g. a `BaseSeeder` subclass); fall back to
     * a parameterless `make()` for vanilla `Seeder` subclasses.
     *
     * @param class-string<Seeder> $class
     * @param array<string, mixed> $parameters
     */
    private function resolveSeeder(string $class, array $parameters): Seeder
    {
        $reflection = new ReflectionClass($class);
        $ctor = $reflection->getConstructor();

        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            return $this->app->make($class);
        }

        return $this->app->make($class, $parameters);
    }
}

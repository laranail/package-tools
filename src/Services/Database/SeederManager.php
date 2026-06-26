<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;

/**
 * Standalone seeding entry point — lets any consumer register and run
 * package seeders WITHOUT using the `Package` builder. Resolved from the
 * container (or via the `PackageSeeder` facade).
 *
 * ```php
 * PackageSeeder::autoSeed('Acme\\Blog', [BlogSeeder::class]); // runs on db:seed
 * $stats = PackageSeeder::seeders()->from($path)->execute();  // run now
 * ```
 */
final class SeederManager
{
    private ?SeederResolverHook $hook = null;

    public function __construct(
        private readonly Application $app,
        private readonly SeederRegistry $registry,
        private readonly SeederExecutor $executor,
        private readonly SeederPathDiscoverer $discoverer,
    ) {}

    /**
     * Register a bundle of seeders to run automatically when the host
     * app's `DatabaseSeeder` resolves (e.g. `php artisan db:seed`).
     *
     * @param list<class-string<Seeder>> $seeders
     * @param array<string, mixed> $options
     */
    public function autoSeed(string $key, array $seeders, ?string $namespace = null, array $options = []): self
    {
        $this->registry->register($key, $seeders, $namespace, $options);

        // Single shared hook; attach() is idempotent so repeated calls are safe.
        $this->hook ??= new SeederResolverHook($this->app, $this->registry, $this->executor);
        $this->hook->attach();

        return $this;
    }

    /**
     * Get a fresh fluent builder bound to the shared registry/executor.
     */
    public function seeders(): SeederBuilder
    {
        return new SeederBuilder($this->registry, $this->executor, $this->discoverer);
    }

    /**
     * Execute everything currently registered.
     */
    public function run(): SeederExecutionStats
    {
        return $this->executor->run($this->registry);
    }

    public function registry(): SeederRegistry
    {
        return $this->registry;
    }
}

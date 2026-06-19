<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Database;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use WeakReference;

/**
 * Hooks the container so that every package's registered seeders run
 * automatically the first time the host app's `DatabaseSeeder` resolves
 * (typically when `php artisan db:seed` runs without `--class`).
 *
 * Idempotent: the same registry can attach repeatedly without stacking
 * duplicate listeners. WeakReference keeps the hook from holding a strong
 * reference to the registry/executor pair.
 */
final class SeederResolverHook
{
    private bool $attached = false;

    public function __construct(
        private readonly Application $app,
        private readonly SeederRegistry $registry,
        private readonly SeederExecutor $executor,
    ) {}

    /**
     * Attach the resolver. The hook fires at most once per request /
     * console invocation, even if multiple packages call `attach()`.
     */
    public function attach(string $rootSeeder = 'Database\\Seeders\\DatabaseSeeder'): self
    {
        if ($this->attached) {
            return $this;
        }
        $this->attached = true;

        $registryRef = WeakReference::create($this->registry);
        $executorRef = WeakReference::create($this->executor);
        $fired = false;

        $listener = function () use ($registryRef, $executorRef, &$fired): void {
            if ($fired) {
                return;
            }

            $registry = $registryRef->get();
            $executor = $executorRef->get();
            if ($registry === null || $executor === null) {
                return;
            }

            $fired = true;
            $executor->run($registry);
        };

        // The host app may resolve `DatabaseSeeder` either by FQCN or
        // by string id; cover both so the hook is reliable across the
        // common call paths Laravel uses.
        $this->app->resolving(Seeder::class, $listener);
        $this->app->resolving($rootSeeder, $listener);

        return $this;
    }

    public function isAttached(): bool
    {
        return $this->attached;
    }
}

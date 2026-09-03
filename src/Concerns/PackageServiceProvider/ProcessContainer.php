<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Foundation\AliasLoader;

/**
 * Applies the container bindings and class aliases a package declared.
 *
 * Runs during REGISTER: after the package's config has merged, so a factory
 * closure can read it, and before `packageRegistered()`, so a consumer's own
 * hook sees a fully bound container.
 */
trait ProcessContainer
{
    /** @var array<string, string> aliases this package asked for but did not get */
    public array $refusedClassAliases = [];

    protected function registerPackageBindings(): self
    {
        foreach ($this->package->bindings as $abstract => $binding) {
            match ($binding['lifetime']) {
                'singleton' => $this->app->singleton($abstract, $binding['concrete']),
                'scoped'    => $this->app->scoped($abstract, $binding['concrete']),
                default     => $this->app->bind($abstract, $binding['concrete']),
            };
        }

        foreach ($this->package->instances as $abstract => $instance) {
            $this->app->instance($abstract, $instance);
        }

        foreach ($this->package->containerAliases as $abstract => $alias) {
            $this->app->alias($abstract, $alias);
        }

        $this->registerPackageClassAliases();

        foreach ($this->package->registerCallbacks as $callback) {
            $callback($this->app, $this->package);
        }

        return $this;
    }

    /**
     * Register global class aliases, without overwriting one that is taken.
     *
     * Laravel's AliasLoader is a flat global map: `alias()` replaces silently,
     * so a package can shadow the application's own alias - or another
     * package's - and the damage surfaces far away as the wrong class being
     * resolved. An alias that is already registered to a DIFFERENT class is
     * therefore refused and recorded, rather than taken.
     *
     * An application that wants the package's alias can either not define its
     * own, or alias the class explicitly in config/app.php, which wins because
     * it was there first.
     */
    protected function registerPackageClassAliases(): void
    {
        if ($this->package->classAliases === []) {
            return;
        }

        $loader = AliasLoader::getInstance();
        $existing = $loader->getAliases();

        foreach ($this->package->classAliases as $alias => $class) {
            $taken = $existing[$alias] ?? null;

            if ($taken !== null && ltrim((string) $taken, '\\') !== ltrim($class, '\\')) {
                $this->refusedClassAliases[$alias] = (string) $taken;

                continue;
            }

            $loader->alias($alias, $class);
        }
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use Closure;
use ReflectionFunction;
use InvalidArgumentException;
use Composer\Autoload\ClassLoader;

/**
 * Which names in a host registry belong to one package, and which shape they must have.
 *
 * Built from the composer name and the package's root PHP namespace:
 *
 * ```php
 * NamingScope::for('laranail/atlas', 'Simtabi\\Laranail\\Atlas\\');
 * ```
 *
 * **Shape.** The default prefix per registry follows the family convention -- `laranail-atlas` for
 * routes, limiters, middleware, gates, Livewire and Blade components; `laranail::atlas` for
 * commands; `laranail/atlas` and `laranail-atlas` for views and translations. A name matches a
 * prefix when it equals it, or continues it with `.`, `:` or `/`. A hyphen does not count as a
 * continuation, because `laranail-atlas-pro.*` is another package's. A sanctioned variant (the
 * `laranail.<slug>.*` route names, say) is passed in `prefixes`, keyed by {@see NameRegistry} value.
 *
 * **Ownership.** A registered name is this package's when any of these holds: what it maps to is a
 * class in the owner namespace; it is a closure whose scope class is, or that was defined under the
 * base path; it is a path under the base path; or the name itself is the bare slug, or continues it
 * (`atlas`, `atlas.page`, `atlas::x`) -- which catches a bare name registered through a closure that
 * carries no evidence of its owner. The base path defaults to the parent of the namespace's PSR-4
 * directory, read from Composer's autoloader -- the package root, so `resources/`, `routes/` and
 * `config/` count as the package's.
 *
 * In the package's own test suite that root also holds `vendor/` (the framework) and `tests/` (the
 * harness), so `vendor/` and `tests/` directly under the base path are never the package's, whatever
 * base path is passed. Without that, every framework closure (`events`, `log`, `router`) and every
 * name the harness defines (a TestCase's `api` limiter) reads as the package's offence.
 */
final readonly class NamingScope
{
    /**
     * Directories directly under the base path that are never the package's: the dependencies its
     * own suite installs, and the suite itself.
     */
    private const array FOREIGN_DIRECTORIES = ['vendor', 'tests'];

    /** @var array<string, list<string>> */
    private array $prefixes;

    private string $vendor;

    private string $slug;

    /**
     * @param array<string, list<string>> $prefixes NameRegistry value => accepted prefixes, replacing
     *                                              the default for that registry.
     */
    private function __construct(
        public string $package,
        public string $ownerNamespace,
        public ?string $basePath,
        array $prefixes,
    ) {
        [$this->vendor, $this->slug] = explode('/', $package, 2);

        $defaults = [];

        foreach (NameRegistry::cases() as $registry) {
            $defaults[$registry->value] = match ($registry) {
                NameRegistry::Command => ["{$this->vendor}::{$this->slug}"],
                NameRegistry::View,
                NameRegistry::Translation    => [$package, "{$this->vendor}-{$this->slug}"],
                NameRegistry::ContainerAlias => ["{$this->vendor}-{$this->slug}", $package, "{$this->vendor}.{$this->slug}"],
                default                      => ["{$this->vendor}-{$this->slug}"],
            };
        }

        $this->prefixes = array_replace($defaults, $prefixes);
    }

    /**
     * @param array<string, list<string>> $prefixes NameRegistry value => accepted prefixes.
     */
    public static function for(string $package, string $ownerNamespace, ?string $basePath = null, array $prefixes = []): self
    {
        if (preg_match('#^[^/\s]+/[^/\s]+$#', $package) !== 1) {
            throw new InvalidArgumentException("A naming scope needs the composer name as vendor/package; [{$package}] is not one.");
        }

        $namespace = trim($ownerNamespace, '\\') . '\\';

        foreach (array_keys($prefixes) as $key) {
            if (NameRegistry::tryFrom((string) $key) === null) {
                throw new InvalidArgumentException("[{$key}] is not a NameRegistry value.");
            }
        }

        return new self($package, $namespace, $basePath ?? self::basePathOf($namespace), $prefixes);
    }

    /**
     * @return list<string>
     */
    public function prefixesFor(NameRegistry $registry): array
    {
        return $this->prefixes[$registry->value];
    }

    /**
     * Whether $name has the shape this registry requires of the package.
     */
    public function matches(NameRegistry $registry, string $name): bool
    {
        // A class-name alias is namespaced by its namespace.
        if ($registry === NameRegistry::ContainerAlias && str_contains($name, '\\')) {
            return true;
        }

        foreach ($this->prefixesFor($registry) as $prefix) {
            if ($this->continues($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $name is the bare slug, or continues it.
     */
    public function looksBare(string $name): bool
    {
        return $this->continues($name, $this->slug);
    }

    /**
     * Whether $evidence -- what a registered name maps to -- shows this package registered it.
     */
    public function owns(mixed $evidence): bool
    {
        return match (true) {
            $evidence instanceof Closure => $this->ownsClosure($evidence),
            is_object($evidence)         => $this->ownsClass($evidence::class),
            is_array($evidence)          => array_any($evidence, fn (mixed $item): bool => $this->owns($item)),
            is_string($evidence)         => $this->ownsClass(explode('@', $evidence, 2)[0]) || $this->ownsPath($evidence),
            default                      => false,
        };
    }

    public function ownsClass(string $class): bool
    {
        return str_starts_with(ltrim($class, '\\') . '\\', $this->ownerNamespace);
    }

    public function ownsPath(string $path): bool
    {
        if ($this->basePath === null || $path === '') {
            return false;
        }

        $base = rtrim(realpath($this->basePath) ?: $this->basePath, '/');
        $resolved = realpath($path) ?: $path;

        if ($resolved !== $base && ! str_starts_with($resolved, $base . '/')) {
            return false;
        }

        $first = explode('/', substr($resolved, strlen($base) + 1), 2)[0];

        return ! in_array($first, self::FOREIGN_DIRECTORIES, true);
    }

    private static function basePathOf(string $namespace): ?string
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $directories = $loader->getPrefixesPsr4()[$namespace] ?? [];

            if ($directories !== []) {
                return dirname((string) (realpath($directories[0]) ?: $directories[0]));
            }
        }

        return null;
    }

    private function continues(string $name, string $prefix): bool
    {
        if ($name === $prefix) {
            return true;
        }

        return str_starts_with($name, $prefix)
            && in_array($name[strlen($prefix)], ['.', ':', '/'], true);
    }

    private function ownsClosure(Closure $closure): bool
    {
        $function = new ReflectionFunction($closure);
        $scope = $function->getClosureScopeClass();

        if ($scope !== null && $this->ownsClass($scope->getName())) {
            return true;
        }

        $file = $function->getFileName();

        return is_string($file) && $this->ownsPath($file);
    }
}

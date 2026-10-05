<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use LogicException;
use ReflectionProperty;
use Illuminate\Routing\Router;
use Illuminate\Cache\RateLimiter;
use Illuminate\View\FileViewFinder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Contracts\Container\Container;

/**
 * Reads the names a booted application holds in each {@see NameRegistry}, each keyed to the thing it
 * maps to -- the evidence {@see NamingScope::owns()} reads to decide whose name it is.
 *
 * Every read is of the live registry, never of a provider's source: a grep proves how a
 * registration was written, not what the framework ended up holding. Three registries have no public
 * accessor (rate limiters, Livewire's finder, container aliases), so those are read by reflection,
 * and a renamed property throws rather than reading as an empty registry.
 */
final readonly class RegisteredNames
{
    public function __construct(private Container $app) {}

    /**
     * @return array<string, mixed> name => evidence
     */
    public function read(NameRegistry $registry): array
    {
        return match ($registry) {
            NameRegistry::Route          => $this->routes(),
            NameRegistry::RateLimiter    => $this->property($this->app->make(RateLimiter::class), 'limiters'),
            NameRegistry::Command        => $this->app->make(Kernel::class)->all(),
            NameRegistry::Middleware     => $this->app->make(Router::class)->getMiddleware(),
            NameRegistry::View           => $this->viewHints(),
            NameRegistry::Translation    => $this->app->make('translator')->getLoader()->namespaces(),
            NameRegistry::Livewire       => $this->livewire(),
            NameRegistry::Gate           => $this->app->make(Gate::class)->abilities(),
            NameRegistry::BladeComponent => $this->bladeComponents(),
            NameRegistry::ContainerAlias => $this->containerAliases(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function property(object $target, string $property): array
    {
        if (! property_exists($target, $property)) {
            throw new LogicException(sprintf(
                '%s has no [$%s] property, so its registry cannot be read. The installed framework has '
                . 'changed it; update laranail/package-tools.',
                $target::class,
                $property,
            ));
        }

        $value = (new ReflectionProperty($target, $property))->getValue($target);

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function routes(): array
    {
        $names = [];

        foreach ($this->app->make(Router::class)->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if (is_string($name) && $name !== '') {
                $names[$name] = $route->getAction('uses');
            }
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewHints(): array
    {
        $finder = $this->app->make('view')->getFinder();

        if (! $finder instanceof FileViewFinder) {
            throw new LogicException('The view finder is ' . $finder::class . ', which exposes no namespace hints to read.');
        }

        return $finder->getHints();
    }

    /**
     * Livewire 4 keeps class components on its finder; Livewire 3 on its component registry.
     *
     * @return array<string, mixed>
     */
    private function livewire(): array
    {
        if ($this->app->bound('livewire.finder')) {
            return $this->property($this->app->make('livewire.finder'), 'classComponents');
        }

        $registry = 'Livewire\\Mechanisms\\ComponentRegistry';

        if (class_exists($registry) && $this->app->bound($registry)) {
            return $this->property($this->app->make($registry), 'aliases');
        }

        throw new LogicException(
            'Livewire is not installed (no livewire.finder and no ComponentRegistry is bound), so there '
            . 'is no Livewire registry to read. Drop the assertion, or install Livewire in the test app.',
        );
    }

    /**
     * Class component aliases, class component namespace prefixes, and anonymous component
     * prefixes, in one map.
     *
     * @return array<string, mixed>
     */
    private function bladeComponents(): array
    {
        /** @var BladeCompiler $blade */
        $blade = $this->app->make('blade.compiler');

        $names = $blade->getClassComponentAliases();

        foreach ($blade->getClassComponentNamespaces() as $prefix => $namespace) {
            $names[$prefix] = rtrim((string) $namespace, '\\') . '\\Component';
        }

        foreach ($blade->getAnonymousComponentNamespaces() as $prefix => $directory) {
            $names[$prefix] = $directory;
        }

        foreach ($blade->getAnonymousComponentPaths() as $path) {
            if (is_array($path) && is_string($path['prefix'] ?? null)) {
                $names[$path['prefix']] = $path['path'] ?? null;
            }
        }

        return $names;
    }

    /**
     * Non-class container aliases and bindings: `laranail-atlas`, `sms`, `helper`. A key that is a
     * class or interface name is namespaced already and is kept, but always matches.
     *
     * @return array<string, mixed>
     */
    private function containerAliases(): array
    {
        $names = [];

        foreach ($this->property($this->app, 'aliases') as $alias => $abstract) {
            $names[(string) $alias] = $abstract;
        }

        foreach ($this->app->getBindings() as $abstract => $binding) {
            $names[(string) $abstract] = $binding['concrete'] ?? null;
        }

        return $names;
    }
}

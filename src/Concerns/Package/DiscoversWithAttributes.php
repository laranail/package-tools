<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Attributes\AsArtisanCommand;
use Simtabi\Laranail\PackageTools\Attributes\AsRoute;
use Simtabi\Laranail\PackageTools\Attributes\AsViewComposer;
use Simtabi\Laranail\PackageTools\Services\Discovery\AttributeDiscoverer;

/**
 * Adds `Package::discoversWithAttributes()` — the Spatie differentiator
 * (ADR-009). Scans the package's src/ for classes carrying #[AsArtisanCommand],
 * #[AsRoute], #[AsViewComposer] attributes and registers them via the
 * existing fluent API (`hasCommand`, etc.).
 *
 * `#[AsFacade]` is handled by FacadeAutoGenerator in Phase 5C and is not
 * processed here (this trait wires the v1.0 trio).
 */
trait DiscoversWithAttributes
{
    /** @var bool Whether attribute-driven discovery is enabled. */
    protected bool $usesAttributeDiscovery = false;

    /** @var string|null Filesystem directory to scan; defaults to packageBasePath('src'). */
    protected ?string $attributeDiscoveryDirectory = null;

    /** @var string|null PSR-4 root namespace; defaults to the package's autoload-detected root. */
    protected ?string $attributeDiscoveryNamespace = null;

    /**
     * Enable attribute-driven discovery. The Package will scan `$directory`
     * (defaulting to packageBasePath('src')) for classes carrying:
     *
     *   - #[AsArtisanCommand]  → registered via hasCommand()
     *   - #[AsRoute]           → registered via the route loader
     *   - #[AsViewComposer]    → registered via hasViewComposer()
     *
     * Discovery runs at packageBooted() time. Calling this method does not
     * by itself perform discovery — it sets a flag the service provider
     * checks during boot.
     */
    public function discoversWithAttributes(?string $directory = null, ?string $namespace = null): static
    {
        $this->usesAttributeDiscovery = true;
        $this->attributeDiscoveryDirectory = $directory;
        $this->attributeDiscoveryNamespace = $namespace;

        return $this;
    }

    public function isUsingAttributeDiscovery(): bool
    {
        return $this->usesAttributeDiscovery;
    }

    public function getAttributeDiscoveryDirectory(): ?string
    {
        return $this->attributeDiscoveryDirectory;
    }

    public function getAttributeDiscoveryNamespace(): ?string
    {
        return $this->attributeDiscoveryNamespace;
    }

    /**
     * Run discovery now. Returns counts per attribute type so callers
     * (typically the service provider) can log a summary.
     *
     * @return array{commands: int, routes: int, view_composers: int}
     */
    public function runAttributeDiscovery(?AttributeDiscoverer $discoverer = null): array
    {
        if (! $this->usesAttributeDiscovery) {
            return ['commands' => 0, 'routes' => 0, 'view_composers' => 0];
        }

        $discoverer ??= new AttributeDiscoverer;
        $directory = $this->attributeDiscoveryDirectory ?? $this->resolveDefaultDiscoveryDirectory();
        $namespace = $this->attributeDiscoveryNamespace ?? $this->resolveDefaultDiscoveryNamespace();

        $counts = ['commands' => 0, 'routes' => 0, 'view_composers' => 0];

        foreach ($discoverer->discover($directory, $namespace, AsArtisanCommand::class) as $hit) {
            $this->registerDiscoveredCommand($hit['class']->getName());
            $counts['commands']++;
        }

        foreach ($discoverer->discover($directory, $namespace, AsRoute::class) as $hit) {
            foreach ($hit['attributes'] as $attr) {
                $this->registerDiscoveredRoute($hit['class']->getName(), $attr->newInstance());
                $counts['routes']++;
            }
        }

        foreach ($discoverer->discover($directory, $namespace, AsViewComposer::class) as $hit) {
            $attr = $hit['attributes'][0]->newInstance();
            $this->registerDiscoveredViewComposer($hit['class']->getName(), $attr->views);
            $counts['view_composers']++;
        }

        return $counts;
    }

    /**
     * Default to packageBasePath('src') when the consumer didn't override.
     */
    protected function resolveDefaultDiscoveryDirectory(): string
    {
        if (method_exists($this, 'packageBasePath')) {
            return $this->packageBasePath('src');
        }

        return getcwd() . '/src';
    }

    /**
     * Default to the package's PSR-4 root. Concrete Package subclasses or
     * tests may override; the fallback is a sensible best-guess based on
     * the package name (e.g., "Acme\Foo" for "acme/foo").
     */
    protected function resolveDefaultDiscoveryNamespace(): string
    {
        if (method_exists($this, 'getNamespace')) {
            return (string) $this->getNamespace();
        }

        return '';
    }

    /**
     * Hook for the consumer trait that owns command registration
     * (HasCommands::hasCommand()). Calling unconditionally — the host
     * Package class composes HasCommands so this method exists.
     */
    protected function registerDiscoveredCommand(string $commandClass): void
    {
        if (method_exists($this, 'hasCommand')) {
            $this->hasCommand($commandClass);
        }
    }

    /**
     * Hook for route registration. Stores the route descriptor in a
     * registry the service provider iterates at boot.
     *
     * @param string $controllerClass FQCN of the AsRoute target.
     * @param AsRoute $route Route descriptor.
     */
    protected function registerDiscoveredRoute(string $controllerClass, AsRoute $route): void
    {
        if (! property_exists($this, 'discoveredRoutes')) {
            $this->discoveredRoutes = [];
        }

        $this->discoveredRoutes[] = [
            'controller' => $controllerClass,
            'method' => $route->method,
            'uri' => $route->uri,
            'name' => $route->name,
            'middleware' => $route->middleware,
        ];
    }

    /**
     * Hook for view-composer registration. Delegates to HasViewComposers
     * if the host class composes it.
     *
     * @param string $composerClass FQCN of the AsViewComposer target.
     * @param string|string[] $views Views to attach to.
     */
    protected function registerDiscoveredViewComposer(string $composerClass, string|array $views): void
    {
        if (method_exists($this, 'hasViewComposer')) {
            foreach ((array) $views as $view) {
                $this->hasViewComposer($view, $composerClass);
            }

            return;
        }

        if (! property_exists($this, 'discoveredViewComposers')) {
            $this->discoveredViewComposers = [];
        }

        $this->discoveredViewComposers[] = [
            'composer' => $composerClass,
            'views' => (array) $views,
        ];
    }

    /**
     * @return array<int, array{controller: string, method: string, uri: string, name: ?string, middleware: array<int, string>}>
     */
    public function getDiscoveredRoutes(): array
    {
        return $this->discoveredRoutes ?? [];
    }

    /**
     * @return array<int, array{composer: string, views: array<int, string>}>
     */
    public function getDiscoveredViewComposers(): array
    {
        return $this->discoveredViewComposers ?? [];
    }
}

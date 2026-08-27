<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Registry;

use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Package\Tools\Package;
use Throwable;

/**
 * Every package built on `PackageServiceProvider`, and what each one claimed.
 *
 * Laravel keeps view namespaces, translation namespaces, config keys, publish tags, command names
 * and middleware aliases in **flat global maps**. A second package claiming a key does not collide
 * loudly — it silently replaces the first, and the damage surfaces far away as a missing view, an
 * untranslated string, or the wrong file published.
 *
 * Nothing in the framework will tell you that happened. This registry is what makes it answerable:
 * each provider records what it claimed as it registers, so the set can be listed and checked for
 * two packages claiming one name.
 */
final class PackageRegistry
{
    /** @var array<string, array{package: Package, provider: class-string}> */
    private array $entries = [];

    /**
     * @param class-string $provider
     */
    public function register(Package $package, string $provider): void
    {
        // Keyed by provider rather than by package name: two providers claiming the SAME package
        // name is itself a finding, and keying by name would hide it by overwriting.
        $this->entries[$provider] = ['package' => $package, 'provider' => $provider];
    }

    /**
     * @return array<string, array{package: Package, provider: class-string}>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * What one package claimed, flattened for display.
     *
     * @return array{name: mixed, provider: string, version: string, config: mixed, views: mixed, translations: mixed, components: mixed, publishTags: list<string>, commands: list<string>, path: string}
     */
    public function describe(string $provider): array
    {
        $package = $this->entries[$provider]['package'];

        return [
            // getSlashNamespace(), not ->name: the property holds the SHORT name ('atlas'), and a
            // report keyed on that cannot distinguish acme/atlas from laranail/atlas -- which is
            // precisely the confusion this class exists to remove.
            'name' => $this->quietly($package->getSlashNamespace(...), $package->name),
            'provider' => $provider,
            'version' => $this->versionOf($package->name),
            'config' => $this->quietly(fn (): string => $package->getDottedNamespace()),
            'views' => $package->hasViews ? $this->quietly($package->viewNamespace(...)) : null,
            'translations' => $package->hasTranslations ? $this->quietly($package->translationNamespace(...)) : null,
            'components' => $package->hasViews ? $this->quietly($package->componentPrefix(...)) : null,
            // Read from the LIVE registry rather than recomputed from the Package: what the
            // framework ended up holding is the only thing that matters for a flat global map, and
            // it is also the only way a tag registered by a direct publishes() call shows up here.
            'publishTags' => $this->publishTagsFor($package),
            'commands' => array_values($package->commands),
            'path' => $package->basePath(),
        ];
    }

    /**
     * Names claimed by more than one package, per registry.
     *
     * This is the question the flat maps cannot answer for you, and the reason the whole class
     * exists — see the class docblock.
     *
     * @return array<string, array<string, list<string>>>
     */
    public function collisions(): array
    {
        /** @var array<string, array<string, list<string>>> $claims */
        $claims = ['config' => [], 'views' => [], 'translations' => [], 'components' => []];

        foreach (array_keys($this->entries) as $provider) {
            $described = $this->describe($provider);

            foreach (array_keys($claims) as $surface) {
                $name = $described[$surface] ?? null;

                if (is_string($name) && $name !== '') {
                    $claims[$surface][$name][] = $described['name'];
                }
            }
        }

        $collisions = [];

        foreach ($claims as $surface => $byName) {
            foreach ($byName as $name => $packages) {
                // Deduplicated: one package registering the same namespace twice is not a collision.
                $packages = array_values(array_unique($packages));

                if (count($packages) > 1) {
                    $collisions[$surface][$name] = $packages;
                }
            }
        }

        return $collisions;
    }

    /**
     * Publish tags the framework is actually holding for this package.
     *
     * @return list<string>
     */
    private function publishTagsFor(Package $package): array
    {
        // getNamespacedPublishTag('x') is getDoubleColonNamespace() . '-x', so this is the prefix
        // every tag the package mints shares. setPublishTagId() does not enter into it.
        $prefix = $this->quietly(fn (): string => $package->getDoubleColonNamespace() . '-', '');

        if (! is_string($prefix) || $prefix === '') {
            return [];
        }

        return array_values(array_filter(
            ServiceProvider::publishableGroups(),
            static fn (string $tag): bool => str_starts_with($tag, $prefix),
        ));
    }

    /**
     * The installed version, from composer's own runtime data rather than a hardcoded constant that
     * would drift. "unknown" when the package is a path repository or not installed by composer.
     */
    private function versionOf(string $name): string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($name)) {
            return 'unknown';
        }

        return InstalledVersions::getPrettyVersion($name) ?? 'unknown';
    }

    /**
     * Several Package accessors throw when the package was configured without a vendor. This report
     * is a diagnostic, so a half-configured package should still appear in it -- reporting nothing
     * because one field is unavailable is exactly the silence this class exists to remove.
     *
     * @template T
     *
     * @param callable(): T $resolve
     * @param T|null $fallback
     * @return T|null
     */
    private function quietly(callable $resolve, mixed $fallback = null): mixed
    {
        try {
            return $resolve();
        } catch (Throwable) {
            return $fallback;
        }
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use Illuminate\Container\Container;

/**
 * Live-registry naming assertions: every name a package registers into a flat host registry carries
 * its vendor and slug, except an explicit list of deprecated aliases.
 *
 * Mix into a Testbench test case that boots the package:
 *
 * ```php
 * uses(AssertsRegisteredNames::class);
 *
 * $scope = NamingScope::for('laranail/installer-web', 'Simtabi\\Laranail\\Installer\\Web\\');
 *
 * it('scopes every public name', function () use ($scope): void {
 *     $this->assertRouteNamesScoped($scope, atLeast: 10);
 *     $this->assertRateLimitersScoped($scope, deprecated: ['installer', 'installer-gate']);
 *     $this->assertLivewireComponentsScoped($scope, deprecated: ['installer-wizard-step']);
 * });
 * ```
 *
 * Each assertion reads the live registry ({@see RegisteredNames}), works out which names are the
 * package's ({@see NamingScope}), and fails on:
 *
 * - an owned name without the scoped shape that is not listed as deprecated;
 * - a deprecated entry that is not registered -- an allow-list entry is a ceiling, and a stale one
 *   is a licence waiting to cover something new;
 * - fewer scoped names than `atLeast` -- a scope that matches nothing passes everything else
 *   trivially. Pass the count you expect, not 1, wherever it is known.
 *
 * Each returns the scoped names it found, for further assertions.
 */
trait AssertsRegisteredNames
{
    /**
     * @param list<string> $deprecated Bare names the package still registers on purpose.
     *
     * @return list<string> The scoped names found.
     */
    protected function assertRegisteredNamesScoped(NameRegistry $registry, NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return NamingAssertions::scoped(Container::getInstance(), $registry, $scope, $deprecated, $atLeast);
    }

    /** @param list<string> $deprecated @return list<string> */
    protected function assertRouteNamesScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::Route, $scope, $deprecated, $atLeast);
    }

    /** @param list<string> $deprecated @return list<string> */
    protected function assertRateLimitersScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::RateLimiter, $scope, $deprecated, $atLeast);
    }

    /**
     * Command names and their aliases: Symfony registers every alias as a key of its own.
     *
     * @param list<string> $deprecated
     *
     * @return list<string>
     */
    protected function assertCommandNamesScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::Command, $scope, $deprecated, $atLeast);
    }

    /** @param list<string> $deprecated @return list<string> */
    protected function assertMiddlewareAliasesScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::Middleware, $scope, $deprecated, $atLeast);
    }

    /** @param list<string> $deprecated @return list<string> */
    protected function assertViewNamespacesScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::View, $scope, $deprecated, $atLeast);
    }

    /** @param list<string> $deprecated @return list<string> */
    protected function assertTranslationNamespacesScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::Translation, $scope, $deprecated, $atLeast);
    }

    /** @param list<string> $deprecated @return list<string> */
    protected function assertLivewireComponentsScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::Livewire, $scope, $deprecated, $atLeast);
    }

    /** @param list<string> $deprecated @return list<string> */
    protected function assertGateAbilitiesScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::Gate, $scope, $deprecated, $atLeast);
    }

    /**
     * Class component aliases, class component namespace prefixes and anonymous component prefixes.
     *
     * @param list<string> $deprecated
     *
     * @return list<string>
     */
    protected function assertBladeComponentsScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::BladeComponent, $scope, $deprecated, $atLeast);
    }

    /** @param list<string> $deprecated @return list<string> */
    protected function assertContainerAliasesScoped(NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        return $this->assertRegisteredNamesScoped(NameRegistry::ContainerAlias, $scope, $deprecated, $atLeast);
    }

    /**
     * Each deprecated bare route name is NOT a registered route, and still generates the same URL as
     * its scoped replacement -- through the missing-route resolver, as an alias should.
     *
     * Deprecation notices raised while resolving are swallowed; assert them separately.
     *
     * @param array<string, string> $bareToScoped
     * @param array<string, array<array-key, mixed>> $parameters bare name => route parameters
     */
    protected function assertDeprecatedRouteNamesResolve(array $bareToScoped, array $parameters = []): void
    {
        NamingAssertions::deprecatedRouteNamesResolve(Container::getInstance(), $bareToScoped, $parameters);
    }
}

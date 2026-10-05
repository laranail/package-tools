<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * The assertions behind {@see AssertsRegisteredNames}, as a class so they are statically analysed
 * and callable without the trait.
 */
final class NamingAssertions
{
    /**
     * @param list<string> $deprecated Bare names the package still registers on purpose.
     *
     * @return list<string> The scoped names found.
     */
    public static function scoped(Container $app, NameRegistry $registry, NamingScope $scope, array $deprecated = [], int $atLeast = 1): array
    {
        $entries = (new RegisteredNames($app))->read($registry);

        $scoped = [];
        $offenders = [];

        foreach ($entries as $name => $evidence) {
            $name = (string) $name;

            if ($scope->matches($registry, $name)) {
                // A class-name container key or Livewire component always has the scoped shape; it
                // counts toward the floor only when it is this package's, or every framework one would.
                if (! str_contains($name, '\\') || $scope->owns($evidence)) {
                    $scoped[] = $name;
                }

                continue;
            }

            // A Livewire name derived from the package's own class (how Filament registers pages and
            // widgets) is as unique as the class, so it is scoped.
            if ($scope->isNamedAfterOwnClass($registry, $name, $evidence)) {
                $scoped[] = $name;

                continue;
            }

            if (in_array($name, $deprecated, true)) {
                continue;
            }

            if ($scope->owns($evidence) || $scope->looksBare($name)) {
                $offenders[] = $name . ' => ' . self::describeEvidence($evidence);
            }
        }

        $stale = array_values(array_diff($deprecated, array_map(strval(...), array_keys($entries))));

        Assert::assertGreaterThanOrEqual($atLeast, count($scoped), sprintf(
            "Expected at least %d %s name(s) scoped to %s (%s); found %d. A scope that matches nothing\n"
            . 'passes every other check trivially -- check the scope, or that the package booted. Found: [%s]',
            $atLeast,
            $registry->value,
            $scope->package,
            implode(', ', $scope->prefixesFor($registry)),
            count($scoped),
            implode(', ', $scoped),
        ));

        Assert::assertSame([], $stale, sprintf(
            "These are listed as deprecated %s aliases of %s but are not registered. Remove them from the\n"
            . "allow-list, or it covers whatever claims the name next:\n  %s",
            $registry->value,
            $scope->package,
            implode("\n  ", $stale),
        ));

        Assert::assertSame([], $offenders, sprintf(
            "These %s names belong to %s but are not scoped (%s) and are not listed as deprecated\n"
            . "aliases. In a flat registry the next package to claim one silently replaces it:\n  %s",
            $registry->value,
            $scope->package,
            implode(', ', $scope->prefixesFor($registry)),
            implode("\n  ", $offenders),
        ));

        return $scoped;
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
    public static function deprecatedRouteNamesResolve(Container $app, array $bareToScoped, array $parameters = []): void
    {
        Assert::assertNotEmpty($bareToScoped, 'No deprecated route names were given, so this assertion proves nothing.');

        $url = $app->make(UrlGenerator::class);
        $router = $app->make(Router::class);
        $failures = [];

        set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);

        try {
            foreach ($bareToScoped as $bare => $scoped) {
                if ($router->has($bare)) {
                    $failures[] = "{$bare} is registered as a route of its own, not resolved as an alias of {$scoped}";

                    continue;
                }

                $arguments = $parameters[$bare] ?? [];

                try {
                    $resolved = $url->route($bare, $arguments);
                } catch (RouteNotFoundException) {
                    $failures[] = "{$bare} does not resolve at all";

                    continue;
                }

                $expected = $url->route($scoped, $arguments);

                if ($resolved !== $expected) {
                    $failures[] = "{$bare} resolves to {$resolved}, not to {$scoped} ({$expected})";
                }
            }
        } finally {
            restore_error_handler();
        }

        Assert::assertSame([], $failures, "Deprecated route names that do not behave as aliases:\n  " . implode("\n  ", $failures));
    }

    private static function describeEvidence(mixed $evidence): string
    {
        return match (true) {
            $evidence instanceof Closure => 'Closure',
            is_object($evidence)         => $evidence::class,
            is_string($evidence)         => $evidence,
            is_array($evidence)          => 'array',
            default                      => get_debug_type($evidence),
        };
    }
}

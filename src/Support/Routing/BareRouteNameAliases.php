<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Routing;

use Closure;
use LogicException;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Simtabi\Laranail\Package\Tools\Enums\DeprecationNotice;

/**
 * Keeps a package's deprecated bare route names resolving after its routes moved to vendor-scoped
 * names, and answers the two questions Laravel's own helpers cannot.
 *
 * Route names live in one flat registry, so a bare `error-pages.preview` collides with any sibling
 * or application route of that name and the later registration silently wins. A package therefore
 * registers `laranail-error-pages.preview` and installs this, which hooks
 * `URL::resolveMissingNamedRoutesUsing()`. Laravel consults that hook only for a name it does NOT
 * hold, so an application's own route under the bare name is never shadowed, and the hook is a
 * runtime callback rather than a replacement route collection, so it survives `route:cache`.
 *
 * ```php
 * BareRouteNameAliases::install(
 *     router: $app->make(Router::class),
 *     url: $app->make(UrlGenerator::class),
 *     package: 'laranail/error-pages',
 *     map: ['error-pages.problem' => 'laranail-error-pages.problem'],   // exact names
 *     prefixes: ['error-pages.' => 'laranail-error-pages.'],            // and/or whole prefixes
 * );
 * ```
 *
 * Or declare it on the Package and let the provider install it:
 * `$package->hasDeprecatedRouteNames(prefixes: ['error-pages.' => 'laranail-error-pages.'])`.
 *
 * What it does, and why each matters:
 *
 * - **Chains.** The generator holds ONE resolver, so installing a second silently discards the
 *   first. The resolver already installed is read through {@see previousResolver()} and consulted
 *   for every name this one does not own. Its answer is accepted only if it is a string: anything
 *   else would be a TypeError against `?string` under strict_types, so it reads as "not resolved".
 * - **Asks the public Router** whether the scoped route exists, never `UrlGenerator::$routes`.
 * - **Announces once per name per process** ({@see DeprecationNotice}), never once per call.
 * - **Answers `has()` and `currentIs()`.** `Route::has()` and `Request::routeIs()` read the route
 *   collection directly and never reach the resolver, so `Route::has('error-pages.preview')`
 *   answers false. There is no hook for either (Laravel 13), so callers ask this object instead.
 *
 * The bare names it serves are deprecated; the earliest release that may stop resolving them is the
 * next minor after 0.1, unless `removal` says otherwise. This class itself is not deprecated.
 */
final class BareRouteNameAliases
{
    /**
     * The protected UrlGenerator property holding the missing-route resolver. Laravel offers a setter
     * and no getter, so chaining reads it; the package's contract test pins it against the installed
     * framework so a rename fails CI rather than a user's boot.
     */
    public const string RESOLVER_PROPERTY = 'missingNamedRouteResolver';

    /** @var array<string, true> "package\0name" => announced */
    private static array $announced = [];

    /** @var (Closure(): LoggerInterface)|null */
    private readonly ?Closure $logger;

    /**
     * @param array<string, string> $map Deprecated bare name => scoped name.
     * @param array<string, string> $prefixes Deprecated bare prefix => scoped prefix, tried in order
     *                                        after the exact map. An empty bare prefix maps every
     *                                        missing name (authkit-preset's shape).
     * @param callable|null $previous The resolver installed before this one.
     * @param LoggerInterface|(Closure(): LoggerInterface)|null $logger Required for
     *                                                                  {@see DeprecationNotice::Log}.
     *                                                                  A closure is resolved per
     *                                                                  notice, so a swapped logger is
     *                                                                  honoured.
     */
    public function __construct(
        private readonly Router $router,
        private readonly UrlGenerator $url,
        private readonly string $package,
        private readonly array $map = [],
        private readonly array $prefixes = [],
        private readonly DeprecationNotice $notice = DeprecationNotice::TriggerError,
        LoggerInterface|Closure|null $logger = null,
        private readonly mixed $previous = null,
        private readonly string $removal = 'the next minor after 0.1',
    ) {
        if ($map === [] && $prefixes === []) {
            throw new InvalidArgumentException(
                "Deprecated route names for [{$package}] map nothing: pass a map, prefixes, or both.",
            );
        }

        foreach ($map as $bare => $scoped) {
            if ($bare === '' || $scoped === '' || $bare === $scoped) {
                throw new InvalidArgumentException(
                    "Deprecated route name [{$bare}] => [{$scoped}] for [{$package}] must map a non-empty name to a different one.",
                );
            }
        }

        foreach ($prefixes as $bare => $scoped) {
            if ($scoped === '' || (string) $bare === $scoped) {
                throw new InvalidArgumentException(
                    "Deprecated route prefix [{$bare}] => [{$scoped}] for [{$package}] needs a non-empty scoped prefix that differs from the bare one.",
                );
            }
        }

        if ($notice === DeprecationNotice::Log && $logger === null) {
            throw new InvalidArgumentException(
                "Deprecated route names for [{$package}] are set to log, but no logger was given.",
            );
        }

        $this->logger = $logger instanceof LoggerInterface ? static fn (): LoggerInterface => $logger : $logger;
    }

    /**
     * The resolver Laravel calls for a name it does not hold.
     */
    public function __invoke(string $name, mixed $parameters = [], ?bool $absolute = true): ?string
    {
        $scoped = $this->scopedFor($name);

        if ($scoped !== null) {
            $this->announce($name, $scoped);

            return $this->url->route($scoped, $parameters ?? [], $absolute ?? true);
        }

        if (! is_callable($this->previous)) {
            return null;
        }

        // A foreign resolver is not ours to trust: anything but a string would be a TypeError
        // against ?string under strict_types, so it reads as "not resolved".
        $resolved = ($this->previous)($name, $parameters, $absolute);

        return is_string($resolved) ? $resolved : null;
    }

    /**
     * Install on $url, chaining whatever resolver it already holds.
     *
     * @param array<string, string> $map
     * @param array<string, string> $prefixes
     * @param LoggerInterface|(Closure(): LoggerInterface)|null $logger
     */
    public static function install(
        Router $router,
        UrlGenerator $url,
        string $package,
        array $map = [],
        array $prefixes = [],
        DeprecationNotice $notice = DeprecationNotice::TriggerError,
        LoggerInterface|Closure|null $logger = null,
        string $removal = 'the next minor after 0.1',
    ): self {
        $aliases = new self($router, $url, $package, $map, $prefixes, $notice, $logger, self::previousResolver($url), $removal);

        $url->resolveMissingNamedRoutesUsing($aliases);

        return $aliases;
    }

    /**
     * The missing-route resolver currently installed on $url, if any.
     *
     * The single place the protected property is read. It throws rather than returning null when
     * the property is gone, because null means "no previous resolver" and would silently discard
     * whichever one another package installed.
     *
     * @throws LogicException If the installed framework no longer has the property.
     */
    public static function previousResolver(UrlGenerator $url): ?callable
    {
        $resolver = self::readGeneratorProperty($url, self::RESOLVER_PROPERTY);

        return is_callable($resolver) ? $resolver : null;
    }

    /**
     * Forget which names were already announced. For test suites; a process announces each name
     * once by design.
     */
    public static function forgetWarnings(): void
    {
        self::$announced = [];
    }

    /**
     * The registered scoped route a deprecated bare $name stands for, or null. Pure: announces nothing.
     */
    public function scopedFor(string $name): ?string
    {
        foreach ($this->candidates($name) as $candidate) {
            if ($this->router->has($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * `Route::has()` that also understands this package's deprecated names.
     *
     * A name the router holds answers true without a notice, exactly as the resolver would never be
     * consulted for it. A deprecated name answers true when its scoped route exists, and is announced.
     */
    public function has(string $name): bool
    {
        if ($this->router->has($name)) {
            return true;
        }

        $scoped = $this->scopedFor($name);

        if ($scoped === null) {
            return false;
        }

        $this->announce($name, $scoped);

        return true;
    }

    /**
     * `Request::routeIs()` / `Router::currentRouteNamed()` that also understands this package's
     * deprecated names and patterns: `demo.item.*` matches a current `laranail-demo.item.show`.
     *
     * A pattern that matches as written answers true without a notice. A deprecated pattern that
     * matches only through its scoped translation is announced.
     */
    public function currentIs(string ...$patterns): bool
    {
        if ($patterns === []) {
            return false;
        }

        if ($this->router->currentRouteNamed(...$patterns)) {
            return true;
        }

        foreach ($patterns as $pattern) {
            foreach ($this->candidates($pattern) as $translated) {
                if ($this->router->currentRouteNamed($translated)) {
                    $this->announce($pattern, $translated);

                    return true;
                }
            }
        }

        return false;
    }

    public function package(): string
    {
        return $this->package;
    }

    /**
     * @return array<string, string>
     */
    public function map(): array
    {
        return $this->map;
    }

    /**
     * @return array<string, string>
     */
    public function prefixes(): array
    {
        return $this->prefixes;
    }

    /**
     * Read a protected property of the generator, failing loudly when it does not exist.
     *
     * @throws LogicException
     */
    private static function readGeneratorProperty(UrlGenerator $url, string $property): mixed
    {
        if (! property_exists($url, $property)) {
            throw new LogicException(sprintf(
                '%s has no [$%s] property. laranail/package-tools reads it to chain missing-route '
                . 'resolvers; the installed framework has changed it, so chaining would silently '
                . 'discard another package\'s resolver. Update package-tools.',
                $url::class,
                $property,
            ));
        }

        return Closure::bind(
            static fn (UrlGenerator $generator): mixed => $generator->{$property},
            null,
            UrlGenerator::class,
        )($url);
    }

    /**
     * The scoped names $name could stand for, in the order they are tried.
     *
     * @return list<string>
     */
    private function candidates(string $name): array
    {
        $candidates = [];

        if (isset($this->map[$name])) {
            $candidates[] = $this->map[$name];
        }

        foreach ($this->prefixes as $bare => $scoped) {
            $bare = (string) $bare;

            // A name already under the scoped prefix is not a bare one; prefixing it again only
            // invents `laranail-auth.laranail-auth.*`.
            if (str_starts_with($name, $scoped) || ! str_starts_with($name, $bare)) {
                continue;
            }

            $candidates[] = $scoped . substr($name, strlen($bare));
        }

        return array_values(array_unique($candidates));
    }

    private function announce(string $bare, string $scoped): void
    {
        if ($this->notice === DeprecationNotice::None) {
            return;
        }

        $key = $this->package . "\0" . $bare;

        if (isset(self::$announced[$key])) {
            return;
        }

        self::$announced[$key] = true;

        $message = sprintf(
            '%s: the route name [%s] is deprecated and will stop resolving no earlier than %s; use [%s].',
            $this->package,
            $bare,
            $this->removal,
            $scoped,
        );

        if ($this->notice === DeprecationNotice::TriggerError) {
            trigger_error($message, E_USER_DEPRECATED);

            return;
        }

        if ($this->logger instanceof Closure) {
            ($this->logger)()->warning($message);
        }
    }
}

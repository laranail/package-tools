<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Psr\Log\LoggerInterface;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Simtabi\Laranail\Package\Tools\Enums\DeprecationNotice;
use Simtabi\Laranail\Package\Tools\Support\Routing\BareRouteNameAliases;

/**
 * Declare the bare route names a package used to register, so they keep resolving to its
 * vendor-scoped routes as deprecated aliases. The provider installs a {@see BareRouteNameAliases}
 * at boot; {@see deprecatedRouteNames()} returns it, for `has()` / `currentIs()`.
 *
 * ```php
 * $package->name('laranail/error-pages')
 *     ->hasDeprecatedRouteNames(prefixes: ['error-pages.' => 'laranail-error-pages.']);
 * ```
 */
trait HasDeprecatedRouteNames
{
    /**
     * @var array{map: array<string, string>, prefixes: array<string, string>, notice: DeprecationNotice, removal: string}|array{}
     */
    protected array $deprecatedRouteNameDeclaration = [];

    protected ?BareRouteNameAliases $installedDeprecatedRouteNames = null;

    /**
     * @param array<string, string> $map Deprecated bare name => scoped name.
     * @param array<string, string> $prefixes Deprecated bare prefix => scoped prefix.
     */
    public function hasDeprecatedRouteNames(
        array $map = [],
        array $prefixes = [],
        DeprecationNotice $notice = DeprecationNotice::TriggerError,
        string $removal = 'the next minor after 0.1',
    ): static {
        $this->deprecatedRouteNameDeclaration = [
            'map'      => $map,
            'prefixes' => $prefixes,
            'notice'   => $notice,
            'removal'  => $removal,
        ];

        return $this;
    }

    /**
     * @return array{map: array<string, string>, prefixes: array<string, string>, notice: DeprecationNotice, removal: string}|array{}
     */
    public function getDeprecatedRouteNames(): array
    {
        return $this->deprecatedRouteNameDeclaration;
    }

    /**
     * The installed fallback, or null when the package declares no deprecated route names (or has
     * not booted yet).
     */
    public function deprecatedRouteNames(): ?BareRouteNameAliases
    {
        return $this->installedDeprecatedRouteNames;
    }

    /**
     * @param Closure(): LoggerInterface $logger Resolved only when a notice is logged.
     */
    public function bootPackageDeprecatedRouteNames(Router $router, UrlGenerator $url, Closure $logger): void
    {
        $declared = $this->deprecatedRouteNameDeclaration;

        if ($declared === []) {
            return;
        }

        $this->installedDeprecatedRouteNames = BareRouteNameAliases::install(
            router: $router,
            url: $url,
            package: $this->deprecatedRouteNamesPackageLabel(),
            map: $declared['map'],
            prefixes: $declared['prefixes'],
            notice: $declared['notice'],
            logger: $logger,
            removal: $declared['removal'],
        );
    }

    private function deprecatedRouteNamesPackageLabel(): string
    {
        return $this->configVendor === null ? $this->shortName() : $this->getSlashNamespace();
    }
}

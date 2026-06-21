<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Fixtures\SamplePackage\Providers;

use Simtabi\Laranail\PackageTools\Package;
use Simtabi\Laranail\PackageTools\Providers\PackageServiceProvider;

/**
 * Fixture provider living at <package-root>/src/Providers, mirroring the
 * conventional Laravel package layout. Used to assert that base-path
 * resolution points at the package root rather than the src/ directory.
 */
final class SampleServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/sample')
            ->setPublishTagId('acme');
    }

    /**
     * Expose the protected base-dir resolver for assertions.
     */
    public function resolvePackageBaseDir(): string
    {
        return $this->getPackageBaseDir();
    }
}

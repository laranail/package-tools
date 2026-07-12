<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Package;

/**
 * HasGates: declarative Gate::define, mirroring HasRateLimiters.
 */
final class HasGatesTest extends TestCase
{
    public function test_registered_gates_are_defined_at_boot(): void
    {
        $package = (new Package)->name('acme/x');
        // nullable user param → the gate also evaluates for guests
        $package->registerGate('manage-session', static fn (?object $user = null): bool => true);
        $package->registerGates([
            'view-dashboard' => static fn (?object $user = null): bool => false,
        ]);

        $this->assertFalse(Gate::has('manage-session'));

        $package->bootPackageGates();

        $this->assertTrue(Gate::has('manage-session'));
        $this->assertTrue(Gate::has('view-dashboard'));
        $this->assertTrue(Gate::allows('manage-session'));
        $this->assertFalse(Gate::allows('view-dashboard'));
    }
}

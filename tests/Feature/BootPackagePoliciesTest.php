<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase;
use Override;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * registerPolicies() on the Package must reach Gate::policy() through
 * bootPackageDeferredHooks() during the provider's boot chain.
 */
final class BootPackagePoliciesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PolicyTestPackageProvider::class];
    }

    public function test_registered_policy_resolves_through_the_gate(): void
    {
        $policy = Gate::getPolicyFor(PolicyFixtureModel::class);

        $this->assertInstanceOf(PolicyFixturePolicy::class, $policy);
    }

    public function test_registered_policy_authorizes_abilities(): void
    {
        // the gate resolves the ability through the registered policy method
        $allowed = Gate::forUser(new PolicyFixtureUser)->allows('view', new PolicyFixtureModel);

        $this->assertTrue($allowed);
    }

    public function test_get_policies_exposes_the_registered_map(): void
    {
        $package = $this->app->make(PolicyTestPackageProvider::class)->package;

        $this->assertSame(
            [PolicyFixtureModel::class => PolicyFixturePolicy::class],
            $package->getPolicies(),
        );
    }
}

final class PolicyFixtureModel extends Model {}

final class PolicyFixtureUser extends User {}

final class PolicyFixturePolicy
{
    public function view(): bool
    {
        return true;
    }
}

final class PolicyTestPackageProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->setName('test/policies');
        $package->basePath = sys_get_temp_dir();

        $package->registerPolicies([PolicyFixtureModel::class => PolicyFixturePolicy::class]);
    }

    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(self::class, fn (): static => $this);
    }
}

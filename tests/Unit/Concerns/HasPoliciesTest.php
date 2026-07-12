<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasPolicies;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * declarative policy registration: storage shapes, fluency, and the boot
 * wiring into the Gate.
 */
final class HasPoliciesTest extends TestCase
{
    use HasPolicies;

    #[Test]
    public function it_registers_a_single_policy(): void
    {
        $this->registerPolicy(PolicyStubPost::class, PolicyStubPostPolicy::class);

        $this->assertSame(
            [PolicyStubPost::class => PolicyStubPostPolicy::class],
            $this->getPolicies(),
        );
    }

    #[Test]
    public function it_registers_a_policy_map(): void
    {
        $this->registerPolicies([
            PolicyStubPost::class => PolicyStubPostPolicy::class,
            PolicyStubComment::class => PolicyStubCommentPolicy::class,
        ]);

        $this->assertCount(2, $this->getPolicies());
        $this->assertSame(PolicyStubCommentPolicy::class, $this->getPolicies()[PolicyStubComment::class]);
    }

    #[Test]
    public function a_later_registration_replaces_the_model_entry(): void
    {
        $this->registerPolicy(PolicyStubPost::class, PolicyStubPostPolicy::class);
        $this->registerPolicy(PolicyStubPost::class, PolicyStubCommentPolicy::class);

        $this->assertSame(
            [PolicyStubPost::class => PolicyStubCommentPolicy::class],
            $this->getPolicies(),
        );
    }

    #[Test]
    public function registration_is_fluent(): void
    {
        $result = $this->registerPolicy(PolicyStubPost::class, PolicyStubPostPolicy::class)
            ->registerPolicies([PolicyStubComment::class => PolicyStubCommentPolicy::class]);

        $this->assertSame($this, $result);
        $this->assertCount(2, $this->getPolicies());
    }

    #[Test]
    public function boot_registers_the_policies_with_the_gate(): void
    {
        $this->registerPolicy(PolicyStubPost::class, PolicyStubPostPolicy::class);

        $this->bootPackagePolicies();

        $this->assertInstanceOf(
            PolicyStubPostPolicy::class,
            Gate::getPolicyFor(PolicyStubPost::class),
        );
    }
}

final class PolicyStubPost {}

final class PolicyStubComment {}

final class PolicyStubPostPolicy {}

final class PolicyStubCommentPolicy {}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;

/**
 * PackageBootException names the failing builder, preserves the cause chain,
 * and exposes structured, REDACTED context for the central handler.
 */
final class PackageBootExceptionTest extends TestCase
{
    public function test_it_names_the_builder_and_preserves_the_cause(): void
    {
        $cause = new RuntimeException('scheme is bad', 7);
        $e = PackageBootException::from('useHttps', BootCriticality::Critical, $cause);

        $this->assertSame('useHttps', $e->builder);
        $this->assertSame(BootCriticality::Critical, $e->criticality);
        $this->assertSame($cause, $e->getPrevious());
        $this->assertSame(7, $e->getCode());
        $this->assertStringContainsString('useHttps', $e->getMessage());
    }

    public function test_context_is_structured_and_redacted(): void
    {
        $e = PackageBootException::from(
            'setLocale',
            BootCriticality::Degradable,
            new RuntimeException('secret-token=abc123 leaked'),
        );

        $context = $e->context();

        $this->assertSame('setLocale', $context['builder']);
        $this->assertSame('Degradable', $context['criticality']);
        $this->assertSame('degraded-and-continued', $context['decision']);
        $this->assertSame(RuntimeException::class, $context['cause_type']);
        // redacted (rule 15): the raw cause message is NOT duplicated into context
        $this->assertArrayNotHasKey('cause', $context);
        $this->assertStringNotContainsString('secret-token', json_encode($context) ?: '');
    }

    public function test_critical_context_decision_is_crashed(): void
    {
        $e = PackageBootException::from('routes', BootCriticality::Critical, new RuntimeException('x'));

        $this->assertSame('crashed', $e->context()['decision']);
    }
}

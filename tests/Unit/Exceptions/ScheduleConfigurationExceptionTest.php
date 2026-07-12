<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Exceptions\ScheduleConfigurationException;

final class ScheduleConfigurationExceptionTest extends TestCase
{
    public function test_command_failure_carries_command_and_cause(): void
    {
        $cause = new RuntimeException('deferred call "nope" does not exist');
        $e = ScheduleConfigurationException::commandFailed('acme:prune', $cause);

        $this->assertStringContainsString('acme:prune', $e->getMessage());
        $this->assertStringContainsString('nope', $e->getMessage());
        $this->assertSame('acme:prune', $e->context['command']);
        $this->assertSame(RuntimeException::class, $e->context['exception']);
        $this->assertSame($cause, $e->getPrevious());
        $this->assertSame(5001, $e->getCode());
    }

    public function test_callback_failure_carries_the_cause(): void
    {
        $e = ScheduleConfigurationException::callbackFailed(new RuntimeException('boom'));

        $this->assertStringContainsString('boom', $e->getMessage());
        $this->assertSame('boom', $e->context['reason']);
        $this->assertSame(5002, $e->getCode());
    }

    public function test_seeder_failure_carries_the_bundle_key(): void
    {
        $e = ScheduleConfigurationException::seederFailed('demo-data', new RuntimeException('bad cadence'));

        $this->assertStringContainsString('demo-data', $e->getMessage());
        $this->assertSame('demo-data', $e->context['bundle']);
        $this->assertSame(5003, $e->getCode());
    }
}

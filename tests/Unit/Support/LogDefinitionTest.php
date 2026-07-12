<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Support\Definitions\LogDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * the log definition: the package author's logging defaults — file
 * placement, rotation, level, format, channel delegation, config gating.
 */
final class LogDefinitionTest extends TestCase
{
    #[Test]
    public function defaults_are_daily_fourteen_days_debug_line(): void
    {
        $definition = LogDefinition::make();

        $this->assertTrue($definition->isEnabled());
        $this->assertSame('daily', $definition->driverValue());
        $this->assertSame(14, $definition->daysValue());
        $this->assertSame('debug', $definition->levelValue());
        $this->assertSame('line', $definition->formatValue());
        $this->assertNull($definition->pathValue());
        $this->assertNull($definition->directoryValue());
        $this->assertNull($definition->channelValue());
        $this->assertNull($definition->permissionValue());
        $this->assertTrue($definition->shouldLog());
    }

    #[Test]
    public function every_mutator_is_reflected_in_to_array(): void
    {
        $definition = LogDefinition::make()
            ->path('/var/log/acme.log')
            ->single()
            ->level('warning')
            ->asJson()
            ->permission(0664)
            ->whenConfig('acme.log_on');

        $array = $definition->toArray();

        $this->assertSame('/var/log/acme.log', $array['path']);
        $this->assertSame('single', $array['driver']);
        $this->assertSame('warning', $array['level']);
        $this->assertSame('json', $array['format']);
        $this->assertSame(0664, $array['permission']);
        $this->assertSame('acme.log_on', $array['gate']['key']);
    }

    #[Test]
    public function daily_and_single_are_mutually_exclusive_last_call_wins(): void
    {
        $this->assertSame('single', LogDefinition::make()->daily(30)->single()->driverValue());
        $this->assertSame('daily', LogDefinition::make()->single()->daily(7)->driverValue());
        $this->assertSame(7, LogDefinition::make()->single()->daily(7)->daysValue());
    }

    #[Test]
    public function directory_is_normalized_without_a_trailing_separator(): void
    {
        $this->assertSame('/var/log', LogDefinition::make()->directory('/var/log/')->directoryValue());
    }

    #[Test]
    public function disabled_switches_should_log_off(): void
    {
        $this->assertFalse(LogDefinition::make()->disabled()->shouldLog());
    }

    #[Test]
    public function when_config_gate_controls_should_log(): void
    {
        config()->set('acme.log_on', false);
        $this->assertFalse(LogDefinition::make()->whenConfig('acme.log_on')->shouldLog());

        config()->set('acme.log_on', true);
        $this->assertTrue(LogDefinition::make()->whenConfig('acme.log_on')->shouldLog());
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $definition = LogDefinition::make()->level('info')->daily(30);

        $this->assertSame($definition->toArray(), json_decode($definition->toJson(), true));
        $this->assertSame($definition->toArray(), $definition->jsonSerialize());
    }
}

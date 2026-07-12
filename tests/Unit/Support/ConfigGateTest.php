<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * the shared config gate: truthy vs not-null judgement of a config key,
 * evaluated lazily at passes() time against the live config repository.
 */
final class ConfigGateTest extends TestCase
{
    #[Test]
    public function truthy_mode_passes_for_truthy_values(): void
    {
        foreach ([true, 1, 'x'] as $value) {
            config()->set('gate.flag', $value);

            $this->assertTrue(
                ConfigGate::make('gate.flag')->passes(),
                sprintf('truthy gate should pass for %s', var_export($value, true)),
            );
        }
    }

    #[Test]
    public function truthy_mode_fails_for_falsy_values(): void
    {
        foreach ([false, 0, '', null] as $value) {
            config()->set('gate.flag', $value);

            $this->assertFalse(
                ConfigGate::make('gate.flag')->passes(),
                sprintf('truthy gate should fail for %s', var_export($value, true)),
            );
        }
    }

    #[Test]
    public function truthy_mode_uses_the_default_for_a_missing_key(): void
    {
        $this->assertTrue(ConfigGate::make('gate.absent')->passes());
        $this->assertTrue(ConfigGate::make('gate.absent', true)->passes());
        $this->assertFalse(ConfigGate::make('gate.absent', false)->passes());
    }

    #[Test]
    public function truthy_is_the_default_mode_and_can_be_restated(): void
    {
        config()->set('gate.flag', false);

        $this->assertFalse(ConfigGate::make('gate.flag')->truthy()->passes());
    }

    #[Test]
    public function not_null_mode_passes_for_falsy_but_configured_values(): void
    {
        foreach ([0, false, ''] as $value) {
            config()->set('gate.setting', $value);

            $this->assertTrue(
                ConfigGate::make('gate.setting')->notNull()->passes(),
                sprintf('not-null gate should pass for %s', var_export($value, true)),
            );
        }
    }

    #[Test]
    public function not_null_mode_fails_for_an_explicit_null(): void
    {
        config()->set('gate.setting');

        $this->assertFalse(ConfigGate::make('gate.setting')->notNull()->passes());
    }

    #[Test]
    public function not_null_mode_fails_for_a_missing_key_regardless_of_default(): void
    {
        $this->assertFalse(ConfigGate::make('gate.absent')->notNull()->passes());
        $this->assertFalse(ConfigGate::make('gate.absent', true)->notNull()->passes());
    }

    #[Test]
    public function key_returns_the_gated_config_key(): void
    {
        $this->assertSame('gate.flag', ConfigGate::make('gate.flag')->key());
    }

    #[Test]
    public function to_array_exposes_key_default_and_mode(): void
    {
        $this->assertSame(
            ['key' => 'gate.flag', 'default' => true, 'mode' => 'truthy'],
            ConfigGate::make('gate.flag')->toArray(),
        );

        $this->assertSame(
            ['key' => 'gate.setting', 'default' => false, 'mode' => 'not_null'],
            ConfigGate::make('gate.setting', false)->notNull()->toArray(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorStatus;
use Simtabi\Laranail\Package\Tools\Support\Definitions\DoctorCheckDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class DoctorCheckDefinitionTest extends TestCase
{
    public function test_callback_factory_runs_the_closure(): void
    {
        $check = DoctorCheckDefinition::callback('cache:ping', fn (): DoctorResult => DoctorResult::pass('pong'));

        $this->assertInstanceOf(DoctorCheck::class, $check);
        $this->assertSame('cache:ping', $check->name());
        $this->assertSame(DoctorStatus::Pass, $check->run()->status);
    }

    public function test_named_and_describe_override_the_inner_check(): void
    {
        $check = DoctorCheckDefinition::phpVersion('8.0.0')
            ->named('runtime:php')
            ->describe('php floor for acme');

        $this->assertSame('runtime:php', $check->name());
        $this->assertSame('php floor for acme', $check->description());
        $this->assertSame(DoctorStatus::Pass, $check->run()->status); // we are on >= 8.4
    }

    public function test_library_factories_produce_working_checks(): void
    {
        $this->assertSame(DoctorStatus::Pass, DoctorCheckDefinition::phpExtensions('json')->run()->status);
        $this->assertSame(DoctorStatus::Fail, DoctorCheckDefinition::phpExtensions('definitely_not_an_ext')->run()->status);

        config()->set('doctor_test.key', 'v');
        $this->assertSame(DoctorStatus::Pass, DoctorCheckDefinition::configPresent(['doctor_test.key'])->run()->status);

        $this->assertSame(DoctorStatus::Pass, DoctorCheckDefinition::writablePaths([sys_get_temp_dir()])->run()->status);
        $this->assertSame(DoctorStatus::Pass, DoctorCheckDefinition::reachable('db:ping', fn (): bool => true)->run()->status);
        $this->assertSame(DoctorStatus::Pass, DoctorCheckDefinition::softDependency(self::class, 'this test', required: true)->run()->status);
    }

    public function test_wrap_gains_the_fluent_surface_for_custom_checks(): void
    {
        $custom = new class implements DoctorCheck
        {
            public function name(): string
            {
                return 'custom';
            }

            public function description(): string
            {
                return 'original';
            }

            public function run(): DoctorResult
            {
                return DoctorResult::warn('meh');
            }
        };

        $check = DoctorCheckDefinition::wrap($custom)->named('custom:renamed');

        $this->assertSame('custom:renamed', $check->name());
        $this->assertSame('original', $check->description());
        $this->assertSame(DoctorStatus::Warn, $check->run()->status);
    }

    public function test_config_gates_control_registration(): void
    {
        config()->set('doctor_test.on', true);
        config()->set('doctor_test.off', false);

        $this->assertTrue(DoctorCheckDefinition::phpVersion('8.0.0')->shouldRegister()); // no gate
        $this->assertTrue(DoctorCheckDefinition::phpVersion('8.0.0')->whenConfig('doctor_test.on')->shouldRegister());
        $this->assertFalse(DoctorCheckDefinition::phpVersion('8.0.0')->whenConfig('doctor_test.off')->shouldRegister());
        $this->assertFalse(DoctorCheckDefinition::phpVersion('8.0.0')->whenConfigNotNull('doctor_test.missing')->shouldRegister());
    }

    public function test_serialization_masks_nothing_important(): void
    {
        $check = DoctorCheckDefinition::phpExtensions(['json'])
            ->named('runtime:extensions')
            ->whenConfig('doctor_test.on');

        $array = $check->toArray();

        $this->assertSame('runtime:extensions', $array['name']);
        $this->assertSame('doctor_test.on', $array['gate']['key']);
        $this->assertStringContainsString('PhpExtensionCheck', $array['check']);
        $this->assertJson($check->toJson());
    }
}

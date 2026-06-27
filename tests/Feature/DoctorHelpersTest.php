<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\CallbackCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\ConfigPresentCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\PhpExtensionCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\PhpVersionCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\ReachabilityCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\SoftDependencyCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\WritablePathCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorReporter;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorStatus;
use Simtabi\Laranail\Package\Tools\Services\Doctor\HealthResponder;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class DoctorHelpersTest extends TestCase
{
    public function test_php_extension_check(): void
    {
        $this->assertSame(DoctorStatus::Pass, (new PhpExtensionCheck('json'))->run()->status);
        $this->assertSame(DoctorStatus::Fail, (new PhpExtensionCheck('no_such_ext_xyz'))->run()->status);
    }

    public function test_php_version_check(): void
    {
        $this->assertSame(DoctorStatus::Pass, (new PhpVersionCheck('5.0.0'))->run()->status);
        $this->assertSame(DoctorStatus::Fail, (new PhpVersionCheck('99.0.0'))->run()->status);
    }

    public function test_writable_path_check_and_disk_warn(): void
    {
        $dir = sys_get_temp_dir() . '/pt-doctor-' . uniqid();
        $this->assertSame(DoctorStatus::Pass, (new WritablePathCheck(['d' => $dir]))->run()->status);
        // An absurd minimum free-space requirement → warn (not fail).
        $this->assertSame(DoctorStatus::Warn, (new WritablePathCheck(['d' => $dir], PHP_INT_MAX))->run()->status);
    }

    public function test_config_present_check(): void
    {
        config()->set('x.present', 'yes');
        $this->assertSame(DoctorStatus::Pass, (new ConfigPresentCheck(['x.present']))->run()->status);
        $this->assertSame(DoctorStatus::Fail, (new ConfigPresentCheck(['MISSING' => 'x.absent']))->run()->status);
        $this->assertSame(DoctorStatus::Warn, (new ConfigPresentCheck(['x.absent'], required: false))->run()->status);
    }

    public function test_soft_dependency_check(): void
    {
        $this->assertSame(DoctorStatus::Pass, (new SoftDependencyCheck(self::class, 'self'))->run()->status);
        $this->assertSame(DoctorStatus::Fail, (new SoftDependencyCheck('No\\Such\\Class', 'dep'))->run()->status);
        $this->assertSame(DoctorStatus::Warn, (new SoftDependencyCheck('No\\Such\\Class', 'dep', required: false))->run()->status);
    }

    public function test_reachability_check_warns_on_throw(): void
    {
        $this->assertSame(DoctorStatus::Pass, (new ReachabilityCheck(static fn (): bool => true, 'r'))->run()->status);
        $this->assertSame(DoctorStatus::Warn, (new ReachabilityCheck(static fn (): bool => false, 'r'))->run()->status);
        $this->assertSame(DoctorStatus::Warn, (new ReachabilityCheck(static function (): bool {
            throw new RuntimeException('boom');
        }, 'r'))->run()->status);
    }

    public function test_callback_check(): void
    {
        $check = new CallbackCheck('c', 'desc', static fn (): DoctorResult => DoctorResult::skip('skipped'));
        $this->assertSame(DoctorStatus::Skip, $check->run()->status);
    }

    public function test_doctor_reporter_renders_and_returns_exit_code(): void
    {
        $output = new BufferedOutput;
        $cmd = $this->makeCommand($output);

        $exit = DoctorReporter::render($cmd, [new PhpExtensionCheck('json', 'demo:json')], json: false);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('demo:json', $output->fetch());
    }

    public function test_doctor_reporter_json_status_matches_health_vocabulary(): void
    {
        $output = new BufferedOutput;
        $exit = DoctorReporter::render($this->makeCommand($output), [new PhpExtensionCheck('json', 'demo:json')], json: true);

        $this->assertSame(Command::SUCCESS, $exit);
        // Same vocabulary as HealthResponder: healthy / degraded (not "ok").
        $this->assertSame('healthy', json_decode($output->fetch(), true)['status']);
    }

    public function test_doctor_reporter_fails_on_failing_check(): void
    {
        $output = new BufferedOutput;
        $exit = DoctorReporter::render($this->makeCommand($output), [new PhpExtensionCheck('no_such_ext_xyz')], json: true);

        $this->assertSame(Command::FAILURE, $exit);
        $decoded = json_decode($output->fetch(), true);
        $this->assertSame('degraded', $decoded['status']);
    }

    public function test_health_responder_status_and_code(): void
    {
        $healthy = HealthResponder::json([new PhpExtensionCheck('json')]);
        $this->assertSame(200, $healthy->getStatusCode());
        $this->assertSame('healthy', $healthy->getData(true)['status']);

        $degraded = HealthResponder::json([new PhpExtensionCheck('no_such_ext_xyz')]);
        $this->assertSame(503, $degraded->getStatusCode());
        $this->assertSame('degraded', $degraded->getData(true)['status']);
    }

    public function test_package_config_namespacing_opt_out(): void
    {
        $package = (new Package)->setName('acme/widget');
        $this->assertTrue($package->hasConfigNamespacing());

        $package->withoutConfigNamespacing();
        $this->assertFalse($package->hasConfigNamespacing());
    }

    public function test_package_translation_alias(): void
    {
        $package = (new Package)->setName('acme/widget')->hasTranslations('widget');
        $this->assertSame('widget', $package->getTranslationAlias());
    }

    private function makeCommand(BufferedOutput $output): Command
    {
        $cmd = new class extends Command
        {
            protected $signature = 'pt:test-doctor';
        };
        $cmd->setLaravel($this->app);
        $cmd->setOutput(new OutputStyle(new ArrayInput([]), $output));

        return $cmd;
    }
}

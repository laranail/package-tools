<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Override;
use Simtabi\Laranail\Package\Tools\Commands\InstallCommand;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\InstallCommandDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class InstallDefinitionTestPackageProvider extends PackageServiceProvider
{
    /** @var list<string> execution ledger shared with the assertions */
    public static array $ran = [];

    public function configurePackage(Package $package): void
    {
        self::$ran = [];

        $package
            ->name('acme/install-def')
            ->hasInstallCommand(
                InstallCommandDefinition::make()
                    ->step('first', static function (InstallCommand $command): void {
                        InstallDefinitionTestPackageProvider::$ran[] = 'first';
                    })
                    ->step('second', static function (InstallCommand $command): void {
                        InstallDefinitionTestPackageProvider::$ran[] = 'second';
                    }),
            )
            ->hasInstallCommand(
                InstallCommandDefinition::make()
                    ->named('acme:visible-setup')
                    ->visible()
                    ->step('noop', static fn (InstallCommand $command): null => null),
            );
    }
}

final class BootPackageInstallDefinitionTest extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [InstallDefinitionTestPackageProvider::class];
    }

    public function test_the_derived_install_command_runs_steps_in_order(): void
    {
        $this->artisan('install-def:install')
            ->expectsOutputToContain('install-def has been installed!')
            ->assertSuccessful();

        $this->assertSame(['first', 'second'], InstallDefinitionTestPackageProvider::$ran);
    }

    public function test_named_visible_definitions_register_under_their_signature(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('acme:visible-setup', $commands);
        $this->assertFalse($commands['acme:visible-setup']->isHidden());

        // the default-signature command stays hidden
        $this->assertArrayHasKey('install-def:install', $commands);
        $this->assertTrue($commands['install-def:install']->isHidden());
    }

    public function test_legacy_callable_form_still_works(): void
    {
        $package = new Package;
        $package->setName('acme/legacy');

        $package->hasInstallCommand(function (InstallCommand $command): void {
            $command->publishConfigFile();
        });

        $this->assertCount(1, $package->consoleCommands);
        $this->assertInstanceOf(Command::class, $package->consoleCommands[0]);
    }
}

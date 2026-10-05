<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Console\Kernel;
use Simtabi\Laranail\Package\Tools\Commands\Command;

/**
 * A command invoked by a deprecated alias says so, in one line, and still runs.
 *
 * Automatic for an alias outside the command's vendor scope -- by the naming convention such an
 * alias exists only for backwards compatibility, so warning needs no adoption step. Opt-in, through
 * deprecatedAliases(), for an alias that is vendor-scoped but retired (a renamed command). A
 * vendor-scoped spelling variant such as `laranail:demo.run` stays silent.
 */
final class AliasWarningDemoCommand extends Command
{
    protected $signature = 'laranail::alias-demo.run';

    protected $aliases = ['alias-demo:run', 'laranail:alias-demo.run', 'laranail::alias-demo.old'];

    public function handle(): int
    {
        $this->line('ran');

        return self::SUCCESS;
    }

    protected function deprecatedAliases(): array
    {
        return ['laranail::alias-demo.old'];
    }
}

final class UnscopedAliasDemoCommand extends Command
{
    protected $signature = 'plain-demo';

    protected $aliases = ['plain-demo-legacy'];

    public function handle(): int
    {
        $this->line('ran');

        return self::SUCCESS;
    }
}

beforeEach(function (): void {
    app(Kernel::class)->registerCommand(new AliasWarningDemoCommand);
    app(Kernel::class)->registerCommand(new UnscopedAliasDemoCommand);
});

it('warns when invoked by an alias outside its vendor scope, and still runs', function (): void {
    Artisan::call('alias-demo:run');
    $output = Artisan::output();

    expect($output)->toContain('Deprecated:')
        ->and($output)->toContain('[alias-demo:run]')
        ->and($output)->toContain('[laranail::alias-demo.run]')
        ->and($output)->toContain('next minor after 0.1')
        ->and($output)->toContain('ran');
});

it('stays silent when invoked by its own name', function (): void {
    Artisan::call('laranail::alias-demo.run');

    expect(Artisan::output())->not->toContain('Deprecated')->toContain('ran');
});

it('stays silent for a vendor-scoped spelling variant', function (): void {
    Artisan::call('laranail:alias-demo.run');

    expect(Artisan::output())->not->toContain('Deprecated')->toContain('ran');
});

it('warns for a vendor-scoped alias the command declares deprecated', function (): void {
    Artisan::call('laranail::alias-demo.old');

    expect(Artisan::output())->toContain('[laranail::alias-demo.old]')->toContain('ran');
});

it('cannot tell a scope for an unscoped command name, so warns only when told', function (): void {
    Artisan::call('plain-demo-legacy');

    expect(Artisan::output())->not->toContain('Deprecated')->toContain('ran');
});

it('applies the same rule to the deprecation check directly', function (): void {
    $command = new AliasWarningDemoCommand;

    expect($command->isDeprecatedAlias('alias-demo:run'))->toBeTrue()
        ->and($command->isDeprecatedAlias('laranail:alias-demo.run'))->toBeFalse()
        ->and($command->isDeprecatedAlias('laranail::alias-demo.old'))->toBeTrue()
        ->and($command->isDeprecatedAlias('laranail::alias-demo.run'))->toBeFalse()
        ->and($command->isDeprecatedAlias('not-an-alias'))->toBeFalse();
});

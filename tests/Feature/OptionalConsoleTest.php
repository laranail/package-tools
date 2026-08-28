<?php

declare(strict_types=1);

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederConsoleFormatter;
use Simtabi\Laranail\Package\Tools\Services\Database\PlainSeederConsoleFormatter;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;

/**
 * package-tools is the base class for the whole family, so anything in its `require` block is
 * installed by every application that installs any package built on it.
 *
 * laranail/console was in there for exactly one class out of roughly 270, which meant an application
 * pulling in, say, database utilities also got a console library it never calls. It is a suggestion
 * now, and this file is what keeps it one.
 */
it('requires no laranail package at runtime', function (): void {
    // The guard that matters. Asserting the manifest rather than the container, because the cost is
    // paid at install time by consumers who never boot the class in question.
    $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true);

    $laranail = array_keys(array_filter(
        $composer['require'],
        fn (string $package): bool => str_starts_with($package, 'laranail/'),
        ARRAY_FILTER_USE_KEY,
    ));

    expect($laranail)->toBeEmpty(
        'a laranail/* entry in require is installed by every consumer of every package built on this base',
    );
});

it('keeps laranail/console available for development and names it as a suggestion', function (): void {
    $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true);

    expect($composer['require-dev'])->toHaveKey('laranail/console')
        ->and($composer['suggest'])->toHaveKey('laranail/console');
});

it('resolves the styled formatter when laranail/console is installed', function (): void {
    // The binding lives in this package's own provider, which Testbench does not auto-load, so it is
    // registered here rather than assumed.
    app()->register(PackageToolsServiceProvider::class);

    expect(class_exists(ConsoleUIFormatter::class))->toBeTrue('the dev environment should have it')
        ->and(app(SeederConsoleFormatterInterface::class))->toBeInstanceOf(SeederConsoleFormatter::class);
});

it('gives the plain formatter the same contract', function (): void {
    // What a consumer without laranail/console gets. Verified against a real --no-dev install as
    // well; this pins the contract so the two implementations cannot drift apart.
    $plain = new PlainSeederConsoleFormatter;

    expect($plain)->toBeInstanceOf(SeederConsoleFormatterInterface::class);

    $plain->initializeSession();
    $plain->displayGroupHeader('Demo', 2);
    $plain->displaySeederSuccess('UserSeeder', 0.12);
    $plain->displaySeederSkipped('OtherSeeder', 'already run');
    $plain->displaySeederError('BadSeeder', new Exception('boom'), 0.01);

    expect($plain->getStatistics())->toBe([
        'groups'     => 1,
        'successful' => 1,
        'failed'     => 1,
        'skipped'    => 1,
    ])->and($plain->getSessionDuration())->toBeGreaterThan(0.0);
});

it('implements every method the interface declares', function (): void {
    $contract = (new ReflectionClass(SeederConsoleFormatterInterface::class))->getMethods();
    $plain = new ReflectionClass(PlainSeederConsoleFormatter::class);

    foreach ($contract as $method) {
        expect($plain->hasMethod($method->getName()))->toBeTrue(
            "PlainSeederConsoleFormatter is missing {$method->getName()}()",
        );
    }
});

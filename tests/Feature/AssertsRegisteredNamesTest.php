<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\AssertionFailedError;
use Simtabi\Laranail\Package\Tools\Testing\NamingScope;
use Simtabi\Laranail\Package\Tools\Testing\NameRegistry;
use Simtabi\Laranail\Package\Tools\Testing\AssertsRegisteredNames;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Naming\DemoCommand;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Naming\DemoComponent;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Naming\DemoRegistrar;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Naming\DemoController;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Naming\DemoMiddleware;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Naming\FakeLivewireFinder;

uses(AssertsRegisteredNames::class);

/**
 * The guard on the guard: each registry assertion must pass a correctly scoped package, fail a bare
 * name the package owns, allow a declared deprecated alias, fail a stale allow-list entry, and fail
 * vacuously small results -- or every package adopting it inherits a green tick instead of a check.
 */
function namingScope(): NamingScope
{
    return NamingScope::for(
        package: 'laranail/naming-demo',
        ownerNamespace: 'Simtabi\\Laranail\\Package\\Tools\\Tests\\Fixtures\\Naming\\',
        basePath: __DIR__ . '/../fixtures/Naming',
    );
}

/**
 * Register one correctly scoped name per registry, and -- when $bare -- one bare name per registry
 * that the package owns by evidence (its class, its closure scope, or its path).
 */
function registerNamingFixtures(bool $bare = false): void
{
    $views = __DIR__ . '/../fixtures/Naming/views';
    $lang = __DIR__ . '/../fixtures/Naming/lang';

    Route::get('/naming/page', [DemoController::class, 'show'])->name('laranail-naming-demo.page');
    RateLimiter::for('laranail-naming-demo.api', DemoRegistrar::limiter());
    app(Kernel::class)->registerCommand(new DemoCommand);
    app('router')->aliasMiddleware('laranail-naming-demo', DemoMiddleware::class);
    View::addNamespace('laranail/naming-demo', $views);
    Lang::addNamespace('laranail/naming-demo', $lang);
    Gate::define('laranail-naming-demo.view', DemoRegistrar::ability());
    Blade::component('laranail-naming-demo::card', DemoComponent::class);
    app()->bind('laranail-naming-demo.service', DemoRegistrar::factory());

    $finder = new FakeLivewireFinder;
    $finder->register('laranail-naming-demo.panel', DemoComponent::class);
    app()->instance('livewire.finder', $finder);

    if (! $bare) {
        app('router')->getRoutes()->refreshNameLookups();

        return;
    }

    Route::get('/naming/bare', [DemoController::class, 'show'])->name('naming-page');
    RateLimiter::for('naming-api', DemoRegistrar::limiter());
    app('router')->aliasMiddleware('naming-guard', DemoMiddleware::class);
    View::addNamespace('naming-views', $views);
    Lang::addNamespace('naming-lang', $lang);
    Gate::define('view-naming', DemoRegistrar::ability());
    Blade::component('naming-card', DemoComponent::class);
    app()->bind('naming-service', DemoRegistrar::factory());
    $finder->register('naming-panel', DemoComponent::class);

    app('router')->getRoutes()->refreshNameLookups();
}

dataset('registries', [
    'routes'               => [NameRegistry::Route, 'naming-page'],
    'rate limiters'        => [NameRegistry::RateLimiter, 'naming-api'],
    'commands and aliases' => [NameRegistry::Command, 'naming-demo:run'],
    'middleware aliases'   => [NameRegistry::Middleware, 'naming-guard'],
    'view namespaces'      => [NameRegistry::View, 'naming-views'],
    'translations'         => [NameRegistry::Translation, 'naming-lang'],
    'gate abilities'       => [NameRegistry::Gate, 'view-naming'],
    'Blade components'     => [NameRegistry::BladeComponent, 'naming-card'],
    'Livewire components'  => [NameRegistry::Livewire, 'naming-panel'],
    'container aliases'    => [NameRegistry::ContainerAlias, 'naming-service'],
]);

it('passes a package whose names are all scoped', function (NameRegistry $registry, string $bare): void {
    registerNamingFixtures();

    // The command registry always holds the deprecated alias the fixture command declares, so it is
    // the one registry where "all scoped" needs the alias listed.
    $deprecated = $registry === NameRegistry::Command ? ['naming-demo:run'] : [];

    $scoped = $this->assertRegisteredNamesScoped($registry, namingScope(), $deprecated);

    expect($scoped)->not->toBeEmpty()->not->toContain($bare);
})->with('registries');

it('fails, naming it, on a bare name the package owns', function (NameRegistry $registry, string $bare): void {
    registerNamingFixtures(bare: true);

    expect(fn () => $this->assertRegisteredNamesScoped($registry, namingScope()))
        ->toThrow(AssertionFailedError::class, $bare);
})->with('registries');

it('allows a bare name listed as a deprecated alias', function (NameRegistry $registry, string $bare): void {
    registerNamingFixtures(bare: true);

    $this->assertRegisteredNamesScoped($registry, namingScope(), [$bare]);

    expect(true)->toBeTrue();
})->with('registries');

it('fails on a deprecated alias that is no longer registered', function (NameRegistry $registry): void {
    // A stale allow-list entry is a licence waiting to cover something new.
    registerNamingFixtures();

    $deprecated = ['naming-gone'];

    if ($registry === NameRegistry::Command) {
        $deprecated[] = 'naming-demo:run';
    }

    expect(fn () => $this->assertRegisteredNamesScoped($registry, namingScope(), $deprecated))
        ->toThrow(AssertionFailedError::class, 'naming-gone');
})->with('registries');

it('refuses to pass vacuously', function (NameRegistry $registry): void {
    registerNamingFixtures();

    $deprecated = $registry === NameRegistry::Command ? ['naming-demo:run'] : [];

    expect(fn () => $this->assertRegisteredNamesScoped($registry, namingScope(), $deprecated, atLeast: 5))
        ->toThrow(AssertionFailedError::class, 'at least 5');
})->with('registries');

it('ignores a bare name the package does not own', function (): void {
    registerNamingFixtures();

    // The application's own route under a generic name is not this package's to judge.
    Route::get('/foreign', fn (): string => 'x')->name('dashboard');
    RateLimiter::for('api', fn (): null => null);

    $this->assertRegisteredNamesScoped(NameRegistry::Route, namingScope());
    $this->assertRegisteredNamesScoped(NameRegistry::RateLimiter, namingScope());

    expect(true)->toBeTrue();
});

it('treats a name that looks like the bare slug as owned, whatever registered it', function (): void {
    registerNamingFixtures();

    // A limiter registered from a closure package-tools built has no evidence of its owner; the
    // bare slug in the name is evidence enough.
    RateLimiter::for('naming-demo.api', fn (): null => null);

    expect(fn () => $this->assertRegisteredNamesScoped(NameRegistry::RateLimiter, namingScope()))
        ->toThrow(AssertionFailedError::class, 'naming-demo.api');
});

it('accepts a sanctioned variant prefix when the scope declares one', function (): void {
    registerNamingFixtures();

    Route::get('/naming/dotted', [DemoController::class, 'show'])->name('laranail.naming-demo.dotted');
    app('router')->getRoutes()->refreshNameLookups();

    expect(fn () => $this->assertRegisteredNamesScoped(NameRegistry::Route, namingScope()))
        ->toThrow(AssertionFailedError::class, 'laranail.naming-demo.dotted');

    $scope = NamingScope::for(
        package: 'laranail/naming-demo',
        ownerNamespace: 'Simtabi\\Laranail\\Package\\Tools\\Tests\\Fixtures\\Naming\\',
        prefixes: [NameRegistry::Route->value => ['laranail-naming-demo', 'laranail.naming-demo']],
    );

    expect($this->assertRegisteredNamesScoped(NameRegistry::Route, $scope, atLeast: 2))
        ->toContain('laranail.naming-demo.dotted');
});

it('does not mistake a sibling package with a longer slug for this one', function (): void {
    registerNamingFixtures();

    // laranail-naming-demo-pro.* belongs to laranail/naming-demo-pro, not to this scope; owned by
    // evidence here, it must be reported, not passed as scoped.
    Route::get('/naming/pro', [DemoController::class, 'show'])->name('laranail-naming-demo-pro.page');
    app('router')->getRoutes()->refreshNameLookups();

    expect(fn () => $this->assertRegisteredNamesScoped(NameRegistry::Route, namingScope()))
        ->toThrow(AssertionFailedError::class, 'laranail-naming-demo-pro.page');
});

it('offers one method per registry', function (): void {
    registerNamingFixtures();
    $scope = namingScope();

    $this->assertRouteNamesScoped($scope);
    $this->assertRateLimitersScoped($scope);
    $this->assertCommandNamesScoped($scope, ['naming-demo:run']);
    $this->assertMiddlewareAliasesScoped($scope);
    $this->assertViewNamespacesScoped($scope);
    $this->assertTranslationNamespacesScoped($scope);
    $this->assertGateAbilitiesScoped($scope);
    $this->assertBladeComponentsScoped($scope);
    $this->assertLivewireComponentsScoped($scope);
    $this->assertContainerAliasesScoped($scope);

    expect(true)->toBeTrue();
});

it('fails loudly when Livewire is not installed', function (): void {
    app()->forgetInstance('livewire.finder');
    app()->offsetUnset('livewire.finder');

    $this->assertLivewireComponentsScoped(namingScope());
})->throws(LogicException::class, 'Livewire');

it('derives the default prefixes from the composer name', function (): void {
    $scope = NamingScope::for('laranail/atlas', 'Simtabi\\Laranail\\Atlas\\');

    expect($scope->prefixesFor(NameRegistry::Route))->toBe(['laranail-atlas'])
        ->and($scope->prefixesFor(NameRegistry::Command))->toBe(['laranail::atlas'])
        ->and($scope->prefixesFor(NameRegistry::View))->toBe(['laranail/atlas', 'laranail-atlas'])
        ->and($scope->matches(NameRegistry::Command, 'laranail::atlas.doctor'))->toBeTrue()
        ->and($scope->matches(NameRegistry::Command, 'atlas:doctor'))->toBeFalse()
        ->and($scope->matches(NameRegistry::Route, 'laranail-atlas'))->toBeTrue()
        ->and($scope->matches(NameRegistry::Route, 'laranail-atlas-extra.x'))->toBeFalse()
        ->and($scope->looksBare('atlas.page'))->toBeTrue()
        ->and($scope->looksBare('atlas'))->toBeTrue()
        ->and($scope->looksBare('atlases'))->toBeFalse();
});

it('refuses a scope that is not vendor/package', function (): void {
    NamingScope::for('atlas', 'Simtabi\\Laranail\\Atlas\\');
})->throws(InvalidArgumentException::class, 'vendor/package');

it('checks that a deprecated route name still resolves to its scoped route', function (): void {
    registerNamingFixtures();

    app('url')->resolveMissingNamedRoutesUsing(
        fn (string $name): ?string => $name === 'naming.page' ? route('laranail-naming-demo.page') : null,
    );

    $this->assertDeprecatedRouteNamesResolve(['naming.page' => 'laranail-naming-demo.page']);

    expect(fn () => $this->assertDeprecatedRouteNamesResolve(['naming.gone' => 'laranail-naming-demo.page']))
        ->toThrow(AssertionFailedError::class, 'naming.gone');
});

it('reports a deprecated route name that is registered for real rather than aliased', function (): void {
    registerNamingFixtures(bare: true);

    // naming-page is a real route, so it shadows nothing and is not an alias: the package still
    // registers the bare name, which is the defect.
    expect(fn () => $this->assertDeprecatedRouteNamesResolve(['naming-page' => 'laranail-naming-demo.page']))
        ->toThrow(AssertionFailedError::class, 'naming-page');
});

/**
 * A package laid out the way every adopter is: its root holds src/ and resources/, and in its own
 * test suite also vendor/ (the framework) and tests/ (the harness). Only the first two are its.
 */
function namingRoot(): string
{
    return (string) realpath(__DIR__ . '/../fixtures/NamingRoot');
}

function namingRootClosure(string $file): Closure
{
    return require namingRoot() . '/' . $file;
}

dataset('root layouts', [
    'explicit root as base path' => [fn (): NamingScope => NamingScope::for(
        package: 'laranail/naming-root',
        ownerNamespace: 'Fixture\\NamingRoot\\',
        basePath: namingRoot(),
    )],
    'base path read from the autoloader' => [function (): NamingScope {
        // What an adopter gets by default: the owner namespace maps to <root>/src.
        ClassLoader::getRegisteredLoaders()[array_key_first(ClassLoader::getRegisteredLoaders())]
            ->addPsr4('Fixture\\NamingRoot\\', namingRoot() . '/src');

        return NamingScope::for('laranail/naming-root', 'Fixture\\NamingRoot\\');
    }],
]);

it('does not count closures under the package root vendor/ or tests/ as the package\'s', function (Closure $scope): void {
    $scope = $scope();

    expect($scope->owns(namingRootClosure('vendor/acme/framework/bindings.php')))->toBeFalse()
        ->and($scope->owns(namingRootClosure('tests/TestCaseLimiter.php')))->toBeFalse()
        ->and($scope->ownsPath(namingRoot() . '/vendor'))->toBeFalse()
        ->and($scope->ownsPath(namingRoot() . '/vendor/acme/framework/bindings.php'))->toBeFalse()
        ->and($scope->ownsPath(namingRoot() . '/tests/TestCaseLimiter.php'))->toBeFalse();
})->with('root layouts');

it('still counts the package\'s own source and resources', function (Closure $scope): void {
    $scope = $scope();

    expect($scope->owns(namingRootClosure('src/registrations.php')))->toBeTrue()
        ->and($scope->ownsPath(namingRoot() . '/resources/views'))->toBeTrue()
        ->and($scope->ownsPath(namingRoot() . '/vendored/notes.php'))->toBeTrue()
        ->and($scope->ownsPath(namingRoot() . '/testsuite.php'))->toBeTrue();
})->with('root layouts');

it('passes a package whose own suite registers framework and harness names beside its own', function (Closure $scope): void {
    $scope = $scope();

    // license-kit's suite reported `events`, `log`, `router` and its TestCase's `api` limiter as its own.
    app()->bind('laranail-naming-root.service', namingRootClosure('src/registrations.php'));
    app()->bind('naming-root-framework', namingRootClosure('vendor/acme/framework/bindings.php'));
    RateLimiter::for('laranail-naming-root.api', namingRootClosure('src/registrations.php'));
    RateLimiter::for('api', namingRootClosure('tests/TestCaseLimiter.php'));

    $this->assertContainerAliasesScoped($scope);
    $this->assertRateLimitersScoped($scope);

    // A bare name the source does register is still caught.
    app()->bind('naming-root-own', namingRootClosure('src/registrations.php'));

    expect(fn () => $this->assertContainerAliasesScoped($scope))
        ->toThrow(AssertionFailedError::class, 'naming-root-own');
})->with('root layouts');

/**
 * Filament registers its pages and widgets with Livewire under a name Livewire derives from the
 * class: the class name itself, Livewire 4's `lw<crc32>` hash for a nameless registration, or the
 * class name kebab-cased and dotted. Each is as unique as the class, so none is a bare name.
 */
function livewireFinderWith(string $name, string $class): void
{
    $finder = new FakeLivewireFinder;
    $finder->register('laranail-naming-demo.panel', DemoComponent::class);
    $finder->register($name, $class);
    app()->instance('livewire.finder', $finder);
}

dataset('names derived from an owned class', [
    'its class name'                => [DemoComponent::class],
    'its class name, leading slash' => ['\\' . DemoComponent::class],
    'Livewire 4 hash name'          => ['lw' . crc32(DemoComponent::class)],
    'Livewire derived name'         => ['simtabi.laranail.package.tools.tests.fixtures.naming.demo-component'],
    // Any class name is namespaced by its namespace, as a container alias's is
    'another owned class\'s name' => [DemoController::class],
]);

it('accepts a Livewire name derived from a class the package owns', function (string $name): void {
    livewireFinderWith($name, DemoComponent::class);

    $scoped = $this->assertLivewireComponentsScoped(namingScope(), atLeast: 2);

    expect($scoped)->toContain('laranail-naming-demo.panel', $name);
})->with('names derived from an owned class');

dataset('bare Livewire names for an owned class', [
    'a bare package name'               => ['foo-panel'],
    'the class basename, kebab-cased'   => ['demo-component'],
    'a dotted tail of the derived name' => ['naming.demo-component'],
    'another class\'s derived name'     => ['simtabi.laranail.package.tools.tests.fixtures.naming.demo-controller'],
]);

it('still fails a bare Livewire name that is not derived from the component\'s own class', function (string $name): void {
    livewireFinderWith($name, DemoComponent::class);

    expect(fn () => $this->assertLivewireComponentsScoped(namingScope()))
        ->toThrow(AssertionFailedError::class, $name);
})->with('bare Livewire names for an owned class');

it('does not count a class-named Livewire component of another package toward the floor', function (): void {
    livewireFinderWith(AssertionFailedError::class, AssertionFailedError::class);

    expect(fn () => $this->assertLivewireComponentsScoped(namingScope(), atLeast: 2))
        ->toThrow(AssertionFailedError::class, 'Expected at least 2');
});

it('does not count a Livewire name derived from another package\'s class toward the floor', function (): void {
    livewireFinderWith('lw' . crc32(AssertionFailedError::class), AssertionFailedError::class);

    expect(fn () => $this->assertLivewireComponentsScoped(namingScope(), atLeast: 2))
        ->toThrow(AssertionFailedError::class, 'Expected at least 2');
});

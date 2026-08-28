<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Registry\PackageRegistry;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;

/**
 * The registry answers the one question Laravel's flat global maps cannot: did two packages claim
 * the same name? A second claimant silently replaces the first, so without this the only symptom is
 * a missing view or the wrong file published, far from the cause.
 */
it('records each package that registers through the base', function (): void {
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/alpha')->hasViews()->hasTranslations();
        }
    })->register();

    expect(app(PackageRegistry::class)->count())->toBe(1);
});

it('shares one registry across providers once the first has registered', function (): void {
    // The ordering that matters, pinned. The binding is created by the first package to register,
    // so resolving PackageRegistry before that yields an unbound throwaway with nothing in it --
    // which is silent, and would make the whole report lie. Anything real (a command at handle()
    // time, an application after boot) resolves long afterwards.
    // Two distinct class declarations, not one in a loop: every `new class` at a single code site
    // is the SAME class, and the registry keys by provider class, so a loop would record one entry.
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/first')->hasViews();
        }
    })->register();

    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/second')->hasViews();
        }
    })->register();

    expect(app(PackageRegistry::class))->toBe(app(PackageRegistry::class))
        ->and(app(PackageRegistry::class)->count())->toBe(2);
});

it('describes what a package claimed', function (): void {
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/beta')->hasViews()->hasTranslations();
        }
    })->register();

    $registry = app(PackageRegistry::class);
    $described = array_map($registry->describe(...), array_keys($registry->all()));
    $beta = collect($described)->firstWhere('name', 'acme/beta');

    expect($beta['config'])->toBe('acme.beta')
        ->and($beta['views'])->toBe('acme/beta')
        ->and($beta['translations'])->toBe('acme/beta')
        // Blade tags cannot hold the slash, so the component prefix is the hyphen form.
        ->and($beta['components'])->toBe('acme-beta')
        ->and($beta['version'])->toBeString();
});

it('reports two packages claiming one view namespace', function (): void {
    // In a real application this is one package silently overwriting another's views, with no error
    // raised anywhere. It is the failure this whole feature exists to surface.
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/one')->hasViews('shared/space');
        }
    })->register();

    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/two')->hasViews('shared/space');
        }
    })->register();

    $collisions = app(PackageRegistry::class)->collisions();

    expect($collisions)->toHaveKey('views')
        ->and($collisions['views'])->toHaveKey('shared/space')
        ->and($collisions['views']['shared/space'])->toContain('acme/one')
        ->and($collisions['views']['shared/space'])->toContain('acme/two');
});

it('is quiet when nothing clashes', function (): void {
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/unique-one')->hasViews();
        }
    })->register();

    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/unique-two')->hasViews();
        }
    })->register();

    expect(app(PackageRegistry::class)->collisions())->toBe([]);
});

it('lists packages through the command', function (): void {
    // The command lives on package-tools' own provider, which this Testbench app does not auto-load.
    app()->register(PackageToolsServiceProvider::class);

    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/listed')->hasViews();
        }
    })->register();

    $this->artisan('laranail::package-tools.packages')
        ->expectsOutputToContain('acme/listed')
        ->assertSuccessful();
});

it('exits non-zero when asked only for clashes and there are some', function (): void {
    app()->register(PackageToolsServiceProvider::class);

    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/x')->hasViews('shared/clash');
        }
    })->register();

    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/y')->hasViews('shared/clash');
        }
    })->register();

    $this->artisan('laranail::package-tools.packages --collisions')->assertFailed();
});

it('survives package-tools own provider registering after a consumer', function (): void {
    // Provider order is not guaranteed. A consumer's package registers, creating the binding and
    // recording into it; package-tools' own provider then registers. If that rebinds the singleton,
    // everything recorded so far is discarded and the report is empty with nothing to explain it.
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('acme/early')->hasViews();
        }
    })->register();

    expect(app(PackageRegistry::class)->count())->toBe(1);

    app()->register(PackageToolsServiceProvider::class);

    expect(app(PackageRegistry::class)->count())->toBe(1, 'the toolkit provider discarded what was already recorded');
});

it('reads description, authors and licence from the package manifest', function (): void {
    // Not asked for again through the builder: composer.json is the copy a package author must keep
    // correct in order to publish, so a second one here would drift from the one composer enforces.
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('laranail/package-tools');
        }
    })->register();

    $registry = app(PackageRegistry::class);
    $described = collect(array_map($registry->describe(...), array_keys($registry->all())))
        ->firstWhere('name', 'laranail/package-tools');

    expect($described['description'])->toBeString()->not->toBeEmpty()
        ->and($described['authors'])->not->toBeEmpty()
        ->and($described['license'])->toBe('MIT')
        ->and($described['version'])->not->toBe('unknown');
});

it('lets the builder override what the manifest says', function (): void {
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('laranail/package-tools')
                ->describedAs('A runtime-facing summary')
                ->maintainedBy('Someone Else')
                ->withStability('experimental')
                ->documentedAt('https://docs.test');
        }
    })->register();

    $registry = app(PackageRegistry::class);
    $described = collect(array_map($registry->describe(...), array_keys($registry->all())))
        ->firstWhere('name', 'laranail/package-tools');

    expect($described['description'])->toBe('A runtime-facing summary')
        ->and($described['authors'])->toBe(['Someone Else'])
        ->and($described['stability'])->toBe('experimental')
        ->and($described['docs'])->toBe('https://docs.test');
});

it('resolves a version rather than reporting unknown for an installed package', function (): void {
    // It reported 'unknown' for everything: versionOf() was passed $package->name, the SHORT name,
    // which composer has never heard of.
    (new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('laranail/package-tools');
        }
    })->register();

    $registry = app(PackageRegistry::class);
    $described = collect(array_map($registry->describe(...), array_keys($registry->all())))
        ->firstWhere('name', 'laranail/package-tools');

    expect($described['version'])->not->toBe('unknown');
});

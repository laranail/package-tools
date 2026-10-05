<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Support\NamespaceForms;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * Views and translations answer to both spellings of the package's own name: the canonical
 * `vendor/package` and the `vendor-package` alias.
 *
 * The slash form is canonical (it names the composer package and nests published overrides by
 * vendor); the hyphen form is what Blade tags can spell, and what fourteen packages registered by
 * hand before the slash form was the default. Registering both means a package can move its call
 * sites to the canonical name without breaking a host still writing the old one.
 */
function namespaceFormsFixture(): string
{
    $base = sys_get_temp_dir() . '/laranail-namespace-forms';

    @mkdir($base . '/resources/views', 0777, true);
    @mkdir($base . '/resources/lang/en', 0777, true);
    file_put_contents($base . '/resources/views/hello.blade.php', 'hello from the package');
    file_put_contents($base . '/resources/lang/en/messages.php', "<?php\n\nreturn ['hello' => 'Hello from the package'];\n");

    return $base;
}

/**
 * @param Closure(Package): void $configure
 */
function bootFormsPackage(Closure $configure): Package
{
    $provider = new class(app()) extends PackageServiceProvider
    {
        public static ?Closure $configure = null;

        public static ?Package $configured = null;

        public function configurePackage(Package $package): void
        {
            (self::$configure)($package);
            self::$configured = $package;
        }
    };

    $provider::$configure = $configure;
    app()->register($provider);

    return $provider::$configured;
}

it('registers translations under the slash name and the hyphen alias by default', function (): void {
    $base = namespaceFormsFixture();

    bootFormsPackage(function (Package $package) use ($base): void {
        $package->name('laranail/forms-default')->hasTranslations();
        $package->basePath = $base;
    });

    $namespaces = Lang::getLoader()->namespaces();

    expect($namespaces)->toHaveKey('laranail/forms-default')
        ->and($namespaces)->toHaveKey('laranail-forms-default')
        ->and($namespaces['laranail-forms-default'])->toBe($namespaces['laranail/forms-default'])
        ->and(__('laranail/forms-default::messages.hello'))->toBe('Hello from the package')
        ->and(__('laranail-forms-default::messages.hello'))->toBe('Hello from the package');
});

it('keeps a declared translation alias alongside both forms', function (): void {
    $base = namespaceFormsFixture();

    bootFormsPackage(function (Package $package) use ($base): void {
        $package->name('laranail/forms-alias')->hasTranslations('forms-alias-short');
        $package->basePath = $base;
    });

    expect(array_keys(Lang::getLoader()->namespaces()))
        ->toContain('laranail/forms-alias', 'laranail-forms-alias', 'forms-alias-short');
});

it('adds the slash form to views registered under the hyphen form of the package name', function (): void {
    // hasViews('laranail-confetti') is how fourteen packages registered views before the slash
    // form was the default. That name is unambiguously the package's own, so the canonical form is
    // added over the same resolved paths.
    $base = namespaceFormsFixture();

    bootFormsPackage(function (Package $package) use ($base): void {
        $package->name('laranail/forms-hyphen')->hasViews('laranail-forms-hyphen');
        $package->basePath = $base;
    });

    $hints = View::getFinder()->getHints();

    expect($hints)->toHaveKey('laranail-forms-hyphen')
        ->and($hints)->toHaveKey('laranail/forms-hyphen')
        ->and($hints['laranail/forms-hyphen'])->toBe($hints['laranail-forms-hyphen'])
        ->and(view('laranail/forms-hyphen::hello')->render())->toBe('hello from the package')
        ->and(view('laranail-forms-hyphen::hello')->render())->toBe('hello from the package');
});

it('keeps registering both forms for default views, as before', function (): void {
    $base = namespaceFormsFixture();

    bootFormsPackage(function (Package $package) use ($base): void {
        $package->name('laranail/forms-views')->hasViews();
        $package->basePath = $base;
    });

    expect(array_keys(View::getFinder()->getHints()))->toContain('laranail/forms-views', 'laranail-forms-views');
});

it('adds nothing to a custom view namespace that is not the package name', function (): void {
    // Unchanged behaviour: a vendored or legacy name is the package's to keep, and inventing the
    // package's slash form beside it is not this helper's call.
    $base = namespaceFormsFixture();

    bootFormsPackage(function (Package $package) use ($base): void {
        $package->name('laranail/forms-custom')->hasViews('acme-legacy-views');
        $package->basePath = $base;
    });

    expect(array_keys(View::getFinder()->getHints()))
        ->toContain('acme-legacy-views')
        ->not->toContain('laranail/forms-custom');
});

it('lists every form the package registers', function (): void {
    $package = (new Package)->name('laranail/listed')->hasViews('laranail-listed')->hasTranslations('listed-short');

    expect($package->viewNamespaces())->toBe(['laranail-listed', 'laranail/listed'])
        ->and($package->translationNamespaces())->toBe(['laranail/listed', 'laranail-listed', 'listed-short'])
        ->and((new Package)->name('laranail/plain')->hasViews()->viewNamespaces())->toBe(['laranail/plain', 'laranail-plain']);
});

it('mirrors a hand-registered hyphen namespace to the slash form in one call', function (): void {
    $base = namespaceFormsFixture();

    app()->register(new class(app()) extends ServiceProvider
    {
        public function boot(): void
        {
            $base = sys_get_temp_dir() . '/laranail-namespace-forms';
            $this->loadViewsFrom($base . '/resources/views', 'laranail-handmade');
            $this->loadTranslationsFrom($base . '/resources/lang', 'laranail-handmade');

            NamespaceForms::mirror($this->app, 'laranail/handmade');
        }
    });

    expect(View::getFinder()->getHints())->toHaveKey('laranail/handmade')
        ->and(Lang::getLoader()->namespaces())->toHaveKey('laranail/handmade')
        ->and(view('laranail/handmade::hello')->render())->toBe('hello from the package')
        ->and(__('laranail/handmade::messages.hello'))->toBe('Hello from the package');
});

it('mirrors a hand-registered slash namespace to the hyphen form too', function (): void {
    namespaceFormsFixture();

    app()->register(new class(app()) extends ServiceProvider
    {
        public function boot(): void
        {
            $base = sys_get_temp_dir() . '/laranail-namespace-forms';
            $this->loadViewsFrom($base . '/resources/views', 'laranail/handmade-slash');

            NamespaceForms::mirror($this->app, 'laranail/handmade-slash');
        }
    });

    expect(View::getFinder()->getHints())->toHaveKey('laranail-handmade-slash')
        ->and(Lang::getLoader()->namespaces())->not->toHaveKey('laranail-handmade-slash');
});

it('refuses a name that is not vendor/package', function (): void {
    NamespaceForms::mirror(app(), 'handmade');
})->throws(InvalidArgumentException::class, 'vendor/package');

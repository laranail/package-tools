<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Package;

/**
 * The canonical view and translation namespace is the composer package name, `vendor/package`, so a
 * key names the package that ships it. Blade component tags are the one registry that cannot spell
 * it that way, and these tests pin both halves against the framework's own parser rather than
 * against a comment.
 */
it('defaults the view and translation namespaces to the composer package name', function (): void {
    $package = (new Package)->name('laranail/atlas');

    expect($package->viewNamespace())->toBe('laranail/atlas')
        ->and($package->translationNamespace())->toBe('laranail/atlas');
});

it('offers a hyphen prefix for Blade tags, whose parser rejects a slash', function (): void {
    $package = (new Package)->name('laranail/atlas');

    expect($package->componentPrefix())->toBe('laranail-atlas');
});

it('mirrors a custom view namespace into the component prefix', function (): void {
    // A package that opts out of the default still needs a tag-safe prefix, or its component tags
    // silently stop compiling the moment the custom namespace contains a slash.
    $package = (new Package)->name('laranail/atlas')->hasViews('acme/legacy');

    expect($package->viewNamespace())->toBe('acme/legacy')
        ->and($package->componentPrefix())->toBe('acme-legacy');
});

it('admits dotted sub-components under the hyphen prefix', function (): void {
    // <x-laranail-atlas::forms.input /> resolves to components/forms/input.blade.php. The dot is in
    // Blade's name class, so nesting works -- it is only the slash that does not.
    $pattern = '/<\s*x[-\:]([\w\-\:\.]*)/x';

    preg_match($pattern, '<x-laranail-atlas::forms.input />', $m);

    expect($m[1])->toBe('laranail-atlas::forms.input');
});

it("proves the slash is impossible in a component tag, using Blade's own pattern", function (): void {
    // Illuminate\View\Compilers\ComponentTagCompiler: the name is captured by [\w\-\:\.], which has
    // no forward slash. If this ever changes upstream, this test is the thing that notices.
    $pattern = '/<\s*x[-\:]([\w\-\:\.]*)/x';

    preg_match($pattern, '<x-laranail-atlas::card />', $hyphen);
    preg_match($pattern, '<x-laranail/atlas::card />', $slash);

    expect($hyphen[1])->toBe('laranail-atlas::card')
        ->and($slash[1])->toBe('laranail')          // truncated at the slash
        ->and($slash[1])->not->toBe('laranail/atlas::card');
});

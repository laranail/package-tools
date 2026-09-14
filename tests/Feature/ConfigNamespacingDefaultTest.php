<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPackage;

/**
 * Pins what `hasConfigFile()` actually registers, because getting this wrong is silent and expensive.
 *
 * `docs/tools/config-namespacing.md` described the default as flat — "`hasConfigFile('foo')` merges
 * `config/foo.php` and you read `config('foo.key')`, exactly like Laravel". That is not what happens,
 * and two packages in the family shipped a config nobody could set because their authors read it:
 * `env-tools` and `env-tools-webui` registered at `laranail.env-kit*` and read the bare key, so every
 * shipped value silently fell back to its inline default — protected keys that were writable, secret
 * masking that masked nothing, a Web UI that could not be switched on.
 *
 * The rule is short: **`setName()` requires `vendor/package` and rejects a bare name**, so
 * `configVendor` is never null in a package that boots. `hasConfigNamespacing()` therefore reduces
 * to the `$configNamespacing` flag, which defaults to **true**. Namespacing is always on unless the
 * package opts out.
 *
 * These assertions exist so the documentation cannot drift away from the behaviour again.
 */
it('namespaces config by default, without the package asking', function (): void {
    $package = (new Package)->name('acme/widget')->hasConfigFile('widget');

    expect($package->hasConfigNamespacing())->toBeTrue()
        ->and($package->getNamespacedConfigKey('widget'))->toBe('acme.widget');
});

it('gives an extra config file its own sub-key rather than colliding', function (): void {
    $package = (new Package)->name('acme/widget');

    // The file named for the package maps to the bare dotted namespace; anything else nests under it.
    expect($package->getNamespacedConfigKey('widget'))->toBe('acme.widget')
        ->and($package->getNamespacedConfigKey('limits'))->toBe('acme.widget.limits');
});

it('registers flat only when the package explicitly opts out', function (): void {
    // This is the ONLY way to get the flat behaviour the docs used to describe as the default.
    $package = (new Package)->name('acme/widget')->withoutConfigNamespacing();

    expect($package->hasConfigNamespacing())->toBeFalse();
});

it('rejects a bare package name, which is why configVendor is never null', function (): void {
    // The load-bearing fact behind all of the above: there is no booted package without a vendor,
    // so there is no package for which namespacing is off by accident.
    expect(fn () => (new Package)->name('widget'))->toThrow(InvalidPackage::class);
});

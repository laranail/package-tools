<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;

/**
 * The package that namespaces everyone else's publish tags must not
 * register a bare one itself. Read from the LIVE registry: the raw
 * publishGroups map is what `vendor:publish` consults, and a flat map is
 * exactly where a bare `package-tools-config` collides with a sibling.
 */
it('publishes its own config under the org-namespaced tag only', function (): void {
    // The base TestCase boots workbench packages, not the tools provider
    // itself — register it so its boot() publishes block runs.
    app()->register(PackageToolsServiceProvider::class);

    $reflection = new ReflectionClass(ServiceProvider::class);
    $groups = array_keys($reflection->getProperty('publishGroups')->getValue());

    $ours = array_values(array_filter(
        $groups,
        fn (int|string $tag): bool => str_contains((string) $tag, 'package-tools'),
    ));

    expect($ours)->toContain('laranail::package-tools-config')
        ->not->toContain('package-tools-config');
});

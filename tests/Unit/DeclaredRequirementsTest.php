<?php

declare(strict_types=1);

/**
 * Every `Illuminate\<Component>` that src/ imports is declared in `require`.
 *
 * Testbench installs the whole framework, so an undeclared component passes every test here and
 * fails only for a consumer that installs package-tools without the rest of Laravel -- a Lumen-style
 * host, a bare `illuminate/*` tool, or a resolver that picks a component version the framework
 * never ships. Measured 2026-10-04 (tooling-hygiene audit H6): routing, cache, pagination,
 * translation and view were imported and undeclared.
 *
 * Exempt, with the reason recorded so the entry is a ceiling rather than a licence:
 *
 * - `Foundation` has no split package; the only way to declare it is `laravel/framework`, which is
 *   decision D3 of the estate-followups plan and not taken yet. 7 imports measured 2026-10-05.
 */
const PACKAGE_TOOLS_UNDECLARABLE_COMPONENTS = ['Foundation' => 7];

/** @return array<string, int> component => import count */
function packageToolsImportedComponents(): array
{
    $components = [];
    $files = 0;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src'));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $files++;

        preg_match_all('/^use\s+Illuminate\\\\([A-Za-z]+)\\\\/m', (string) file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $component) {
            $components[$component] = ($components[$component] ?? 0) + 1;
        }
    }

    // Non-vacuity: a scan that found no source proves nothing about it.
    expect($files)->toBeGreaterThan(100);

    return $components;
}

it('declares every illuminate component that src/ imports', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $required = array_keys($composer['require']);

    $components = packageToolsImportedComponents();
    expect(count($components))->toBeGreaterThan(5);

    $missing = [];

    foreach (array_keys($components) as $component) {
        if (array_key_exists($component, PACKAGE_TOOLS_UNDECLARABLE_COMPONENTS)) {
            continue;
        }

        $package = 'illuminate/' . strtolower($component);

        if (! in_array($package, $required, true)) {
            $missing[] = $package;
        }
    }

    expect($missing)->toBe([]);
});

it('keeps each exemption at or below its recorded measurement', function (): void {
    // A stale exemption fails instead of quietly covering new imports.
    $components = packageToolsImportedComponents();

    foreach (PACKAGE_TOOLS_UNDECLARABLE_COMPONENTS as $component => $ceiling) {
        expect($components[$component] ?? 0)->toBeLessThanOrEqual($ceiling);
    }
});

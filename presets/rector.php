<?php

declare(strict_types=1);

use Rector\Set\ValueObject\SetList;
use Rector\Configuration\RectorConfigBuilder;

/**
 * The rule sets every laranail package runs, in one place.
 *
 * `rector.php` had 32 distinct variants across 38 packages -- effectively no
 * shared baseline at all, so a rule enabled in one package said nothing about
 * any other. Rector's config is a fluent builder rather than a file that can be
 * included, so this ships as a callable the package applies to its own builder.
 *
 * What stays per-package is what is genuinely per-package: paths, skips, and the
 * PHP level, which differs across the family (^8.3 in enumerator, ^8.5 in chrono
 * and validation).
 *
 * Usage in a package's `rector.php`:
 *
 *     $laranail = require __DIR__ . '/vendor/laranail/package-tools/presets/rector.php';
 *
 *     return $laranail(
 *         RectorConfig::configure()
 *             ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
 *             ->withSkip([__DIR__ . '/tests/fixtures'])
 *             ->withPhpSets(php84: true)
 *     );
 *
 * Vendor is skipped here rather than in every caller, since no package has ever
 * wanted Rector inside it.
 */
return static function (RectorConfigBuilder $config): RectorConfigBuilder {
    return $config
        ->withSets([
            SetList::CODE_QUALITY,
            SetList::DEAD_CODE,
            SetList::TYPE_DECLARATION,
            SetList::EARLY_RETURN,
        ])
        ->withImportNames(removeUnusedImports: true);
};

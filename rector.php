<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DowngradePhp84\Rector\MethodCall\DowngradeNewMethodCallWithoutParenthesesRector;

$laranail = require __DIR__ . '/presets/rector.php';

return $laranail(
    RectorConfig::configure()
        ->withPaths([
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ])
    // Skip vendor and test fixtures (fixtures are intentional test data —
    // Rector's dead-code rules would strip empty fixture methods/params).
        ->withSkip([
            __DIR__ . '/vendor',
            __DIR__ . '/tests/fixtures',
        ])
        ->withPhpSets(php83: true)
    // Keep the codebase parseable on the 8.3 floor: wrap PHP 8.4
    // "new X()->method()" expressions so they don't break older minors.
        ->withRules([
            DowngradeNewMethodCallWithoutParenthesesRector::class,
        ]),
);

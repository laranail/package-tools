<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Support\Path\PathDirection;
use Simtabi\Laranail\Package\Tools\Support\Path\PathResolver;

/** Lives three directories deep so a test can assert the climb lands where the caller expects. */
final class PathResolverCallerFixture
{
    public static function climbTwo(): string
    {
        return PathResolver::resolve(levels: 2, direction: PathDirection::Outer);
    }

    public static function climbTwoInto(string $path): string
    {
        return PathResolver::resolve(levels: 2, direction: PathDirection::Outer, path: $path);
    }

    public static function ownDirectory(): string
    {
        return __DIR__;
    }
}

<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Support\Path\PathResolver;
use Simtabi\Laranail\Package\Tools\Support\Path\PathDirection;

/**
 * Lives three directories deep so a test can assert the climb lands where the *caller* expects, and
 * exposes descents that run from an arbitrary root so the symlink and missing-entry cases can be
 * driven from a directory this file controls.
 */
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

    /** Three directories deeper than the test file, and must still find the same package root. */
    public static function packageRoot(): string
    {
        return PathResolver::packageRoot();
    }

    public static function ownDirectory(): string
    {
        return __DIR__;
    }

    /** Descends a/b from the fixture root, which is two levels above this file's grandparent. */
    public static function descendFrom(string $root): string
    {
        return (new PathResolverRootedCaller($root))->descend(2, 'a/b');
    }

    public static function descendInto(string $root, string $segment): string
    {
        return (new PathResolverRootedCaller($root))->descend(1, $segment);
    }
}

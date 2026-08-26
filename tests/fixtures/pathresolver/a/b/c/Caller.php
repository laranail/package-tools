<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Support\Path\PathDirection;
use Simtabi\Laranail\Package\Tools\Support\Path\PathResolver;

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

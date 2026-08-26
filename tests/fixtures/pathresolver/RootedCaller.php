<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Support\Path\PathDirection;
use Simtabi\Laranail\Package\Tools\Support\Path\PathResolver;

/**
 * Calls PathResolver from THIS file, whose directory is the fixture root -- so an Inner resolution
 * walks the fixture tree rather than the test directory. The $root argument is asserted against that
 * so a test cannot accidentally point the walk somewhere else.
 */
final class PathResolverRootedCaller
{
    public function __construct(private readonly string $root)
    {
        if (realpath($this->root) !== __DIR__) {
            throw new LogicException('This fixture only descends from its own directory.');
        }
    }

    public function descend(int $levels, string $path): string
    {
        return PathResolver::resolve(levels: $levels, direction: PathDirection::Inner, path: $path);
    }
}

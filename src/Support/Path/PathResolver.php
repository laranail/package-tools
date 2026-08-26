<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Path;

use InvalidArgumentException;
use RuntimeException;

/**
 * Resolve a path relative to the file that called it, by an explicit number of levels.
 *
 * Replaces the `__DIR__ . '/../../config/db-tools.php'` idiom, whose failure mode is that the dot-dot
 * count is invisible: it is correct only for the file's current depth, it is not checked by anything,
 * and moving the file leaves a string that still parses, still looks plausible, and resolves to a
 * path that does not exist. That is precisely how a provider moving into Providers/ broke config
 * loading across this family.
 *
 *     PathResolver::resolve(levels: 2, direction: PathDirection::Outer, path: 'config/db-tools.php')
 *
 * Both `levels` and `direction` are required and are validated before anything is resolved, so a
 * malformed call throws at the call site rather than returning a wrong-but-usable string.
 */
final class PathResolver
{
    /**
     * @param int $levels How many directories to walk. Must be >= 1.
     * @param PathDirection $direction Outer climbs toward the root; Inner descends into `$path`.
     * @param string $path Optional relative path appended after the walk.
     *
     * @throws InvalidArgumentException When levels or the path are unusable.
     * @throws RuntimeException When the walk escapes the filesystem root, or an Inner
     *                          resolution would land outside the calling file's directory.
     */
    public static function resolve(int $levels, PathDirection $direction, string $path = ''): string
    {
        // Fail on the arguments before touching the filesystem: a bad level count that silently
        // clamps is the same class of bug this helper exists to remove.
        if ($levels < 1) {
            throw new InvalidArgumentException(
                sprintf('Levels must be at least 1, got %d. A zero-level walk is the calling directory itself; say so with levels: 1 and an empty path.', $levels)
            );
        }

        if (str_contains($path, '..')) {
            throw new InvalidArgumentException(
                sprintf('The path "%s" contains "..". Express the climb with levels and direction, not with dot-dot segments -- mixing the two is what makes these paths unreadable.', $path)
            );
        }

        $base = self::callerDirectory();

        $resolved = $direction === PathDirection::Outer
            ? self::climb($base, $levels)
            : self::descend($base, $levels, $path);

        if ($direction === PathDirection::Outer && $path !== '') {
            $resolved .= DIRECTORY_SEPARATOR . ltrim($path, '/\\');
        }

        return $resolved;
    }

    /**
     * The directory of the file that called resolve().
     *
     * Deliberately the calling *file*, not the calling class: a trait's methods, a closure and an
     * inherited method all report a class that lives somewhere other than the file on disk, and it is
     * the file's location on disk that the caller is counting levels from.
     */
    private static function callerDirectory(): string
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;

            if (is_string($file) && $file !== '' && dirname($file) !== __DIR__) {
                return dirname($file);
            }
        }

        throw new RuntimeException(
            'Unable to determine the calling file. PathResolver resolves relative to the file that calls it, so it cannot be used from eval()d code or a native callback with no file frame.'
        );
    }

    /**
     * @param int<1, max> $levels
     */
    private static function climb(string $base, int $levels): string
    {
        // dirname() saturates at the root rather than failing, so asking for more levels than exist
        // returns "/" and every path built on it is silently wrong. Check the depth up front -- the
        // return value cannot be used to detect this, because "/" is also a legitimate result of a
        // correct climb from a shallow directory.
        $depth = count(array_filter(explode(DIRECTORY_SEPARATOR, trim($base, DIRECTORY_SEPARATOR))));

        if ($levels >= $depth) {
            throw new RuntimeException(
                sprintf('Climbing %d level(s) from "%s" runs past the filesystem root; it is only %d level(s) deep.', $levels, $base, $depth)
            );
        }

        return dirname($base, $levels);
    }

    /**
     * @param int<1, max> $levels
     */
    private static function descend(string $base, int $levels, string $path): string
    {
        if ($path === '') {
            throw new InvalidArgumentException(
                'An Inner resolution needs a path to descend into -- levels alone cannot name a child directory.'
            );
        }

        $segments = array_values(array_filter(preg_split('#[/\\\\]#', $path) ?: []));

        if (count($segments) !== $levels) {
            throw new InvalidArgumentException(
                sprintf('Descending %d level(s) needs a path of exactly %d segment(s); "%s" has %d.', $levels, $levels, $path, count($segments))
            );
        }

        return $base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }
}

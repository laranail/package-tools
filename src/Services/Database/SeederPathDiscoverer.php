<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Database;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Walks a directory of seeder source files and yields the FQCNs of every
 * concrete `Seeder` subclass it can resolve.
 *
 * Tokeniser-based — no autoloader required. Files are required via
 * `require_once` only on demand by callers; the discoverer itself is
 * side-effect-free aside from reading file contents.
 */
final class SeederPathDiscoverer
{
    /**
     * Discover every Seeder subclass declared in `$path`.
     *
     * @return list<class-string<Seeder>>
     */
    public function discover(string $path): array
    {
        if (! File::isDirectory($path)) {
            throw new InvalidArgumentException(
                "Seeder path does not exist or is not a directory: {$path}"
            );
        }

        $found = [];
        foreach (glob(rtrim($path, '/') . '/*.php') ?: [] as $file) {
            foreach ($this->classesIn($file) as $fqcn) {
                if (class_exists($fqcn) && is_subclass_of($fqcn, Seeder::class)) {
                    $found[] = $fqcn;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return list<string> Fully-qualified class names declared in `$file`.
     */
    public function classesIn(string $file): array
    {
        if (! File::isFile($file)) {
            return [];
        }

        $source = File::get($file);
        $namespace = $this->extractNamespace($source);
        $classes = [];

        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 2; $i < $count; $i++) {
            if (! is_array($tokens[$i - 2])) {
                continue;
            }
            if ($tokens[$i - 2][0] !== T_CLASS) {
                continue;
            }
            if (! is_array($tokens[$i - 1])) {
                continue;
            }
            if ($tokens[$i - 1][0] !== T_WHITESPACE) {
                continue;
            }
            if (! is_array($tokens[$i])) {
                continue;
            }
            if ($tokens[$i][0] !== T_STRING) {
                continue;
            }
            $name = $tokens[$i][1];
            $classes[] = $namespace === '' ? $name : "{$namespace}\\{$name}";
        }

        return $classes;
    }

    private function extractNamespace(string $source): string
    {
        if (preg_match('/^\s*namespace\s+([^;{]+)\s*[;{]/m', $source, $m) === 1) {
            return trim($m[1]);
        }

        return '';
    }
}

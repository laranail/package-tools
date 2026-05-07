<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Discovery;

use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use SplFileInfo;

/**
 * Walks a directory tree, parses PHP files, and yields ReflectionClass
 * objects for every class carrying the requested attribute.
 *
 * Used by Package::discoversWithAttributes() (ADR-009) to wire commands,
 * routes, facades, and view composers without explicit `hasCommand()` calls.
 *
 * Stdlib-only — no spatie/laravel-auto-discoverer dependency.
 */
final class AttributeDiscoverer
{
    /**
     * Yield every class under $directory that carries $attributeClass.
     *
     * @template T of object
     *
     * @param string $directory Absolute path to scan recursively.
     * @param string $rootNamespace PSR-4 root for $directory (e.g. "App\\").
     * @param class-string<T> $attributeClass Attribute to look for.
     * @return Generator<int, array{class: ReflectionClass<object>, attributes: list<ReflectionAttribute<T>>}>
     */
    public function discover(string $directory, string $rootNamespace, string $attributeClass): Generator
    {
        if (! is_dir($directory)) {
            throw new RuntimeException(
                "AttributeDiscoverer: directory does not exist: {$directory}"
            );
        }

        $rootNamespace = rtrim($rootNamespace, '\\') . '\\';
        $directory = rtrim($directory, '/');

        foreach ($this->phpFiles($directory) as $file) {
            $className = $this->classNameFromFile($file, $directory, $rootNamespace);
            if ($className === null) {
                continue;
            }

            try {
                $rc = new ReflectionClass($className);
            } catch (ReflectionException) {
                continue;
            }

            $attrs = $rc->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);
            if ($attrs === []) {
                continue;
            }

            yield [
                'class' => $rc,
                'attributes' => $attrs,
            ];
        }
    }

    /**
     * Walk $directory and yield every *.php file (depth-first).
     *
     * @return Generator<SplFileInfo>
     */
    private function phpFiles(string $directory): Generator
    {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iter as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    /**
     * Derive a fully-qualified class name from a file path under a PSR-4 root.
     *
     * Returns null when the file's path/namespace can't be resolved (e.g.,
     * file outside $directory, or no class declared).
     */
    private function classNameFromFile(SplFileInfo $file, string $directory, string $rootNamespace): ?string
    {
        $absolute = $file->getPathname();
        if (! str_starts_with($absolute, $directory . '/')) {
            return null;
        }

        $relative = substr($absolute, strlen($directory) + 1);
        $relative = substr($relative, 0, -strlen('.php'));

        $fqcn = $rootNamespace . str_replace('/', '\\', $relative);

        return $fqcn !== $rootNamespace ? $fqcn : null;
    }
}

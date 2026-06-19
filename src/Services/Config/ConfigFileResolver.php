<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Config;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\PackageTools\Contracts\ResolverInterface;
use Simtabi\Laranail\PackageTools\Support\PathResolver;

/**
 * Resolves configuration file paths, including nested configs.
 */
class ConfigFileResolver implements ResolverInterface
{
    public function __construct(protected string $basePath) {}

    /**
     * Resolve a configuration file path
     *
     * @param string $file Config file name (without .php extension)
     * @return string Full path to config file
     */
    public function resolve(string $file): string
    {
        $file = trim($file, '/\\');
        // `..` segments and null bytes would let an embedded value escape the
        // package's config/ tree once joinPaths()/normalizePath() collapse
        // them; reject before building the path.
        PathResolver::validatePathSecurity($file);
        $path = PathResolver::joinPaths($this->basePath, 'config', $file . '.php');

        return PathResolver::normalizePath($path);
    }

    /**
     * Resolve a nested configuration file path
     *
     * @param string $file Config file name (without .php extension)
     * @param string $folder Subdirectory within config folder
     * @return string Full path to config file
     */
    public function resolveNested(string $file, string $folder): string
    {
        $folder = trim($folder, '/\\');
        $file = trim($file, '/\\');

        if ($folder === '' || $folder === '0') {
            return $this->resolve($file);
        }

        PathResolver::validatePathSecurity($folder);
        PathResolver::validatePathSecurity($file);
        $path = PathResolver::joinPaths($this->basePath, 'config', $folder, $file . '.php');

        return PathResolver::normalizePath($path);
    }

    /**
     * Check if a configuration file exists
     *
     * @param string $file Config file name
     */
    public function exists(string $file): bool
    {
        $path = $this->resolve($file);

        return File::exists($path);
    }

    /**
     * Get all configuration files in a directory
     *
     * @param string $directory Directory path relative to config
     * @return array<string> Array of config file names
     */
    public function getAllInDirectory(string $directory = ''): array
    {
        $directory = trim($directory, '/\\');
        if ($directory !== '') {
            PathResolver::validatePathSecurity($directory);
        }
        $configPath = PathResolver::joinPaths($this->basePath, 'config', $directory);

        if (! File::isDirectory($configPath)) {
            return [];
        }

        $files = File::files($configPath);
        $configFiles = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $configFiles[] = $file->getFilenameWithoutExtension();
            }
        }

        return $configFiles;
    }

    /**
     * {@inheritDoc}
     */
    public function canResolve(string $input): bool
    {
        return $input !== '' && $input !== '0';
    }
}

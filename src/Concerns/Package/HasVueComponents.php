<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\Component\ComponentRegistry;

/**
 * HasVueComponents - Vue.js component support
 *
 * Enables registering Vue.js components for publishing
 */
trait HasVueComponents
{
    protected ?ComponentRegistry $vueComponentRegistry = null;

    protected array $vueComponents = [];

    /**
     * Register Vue component for publishing
     *
     * @param string $name Component name
     * @param string $path Component file path
     */
    public function hasVueComponent(string $name, string $path): static
    {
        $registry = $this->getVueComponentRegistry();

        $fullPath = $this->packageBasePath($path);

        $registry->register('vue', [$name => $fullPath]);
        $this->vueComponents[$name] = $fullPath;

        return $this;
    }

    /**
     * Register multiple Vue components
     *
     * @param array<string, string> $components [name => path]
     */
    public function hasVueComponents(array $components): static
    {
        foreach ($components as $name => $path) {
            $this->hasVueComponent($name, $path);
        }

        return $this;
    }

    /**
     * Register Vue components from directory
     *
     * @param string $directory Directory path (relative to package root)
     * @param string|null $namespace Component namespace prefix
     */
    public function hasVueComponentsDirectory(string $directory, ?string $namespace = null): static
    {
        $fullPath = $this->packageBasePath($directory);

        if (is_dir($fullPath)) {
            $files = glob($fullPath . '/*.vue') ?: [];

            foreach ($files as $file) {
                $name = basename($file, '.vue');
                $componentName = $namespace ? "{$namespace}/{$name}" : $name;

                $this->hasVueComponent($componentName, str_replace($this->packageBasePath(), '', $file));
            }
        }

        return $this;
    }

    /**
     * Publish Vue components to resources directory
     *
     * @param string $targetDir Target directory (e.g., 'js/components')
     */
    public function publishVueComponents(string $targetDir = 'js/components'): static
    {
        if (empty($this->vueComponents)) {
            return $this;
        }

        foreach ($this->vueComponents as $name => $sourcePath) {
            $fileName = str_replace('/', '-', $name) . '.vue';
            $targetPath = resource_path($targetDir . '/' . $fileName);
            // Delegate to HasAssetPublisher::publishAssets (the canonical
            // multi-arg form). Array-form abstract removed in Phase 3 to
            // avoid collision under ConfiguresAssets — see ADR-004.
            $this->publishAssets($sourcePath, $targetPath, false, 'vue-components');
        }

        return $this;
    }

    /**
     * Get registered Vue components
     *
     * @return array<string, string>
     */
    public function getVueComponents(): array
    {
        return $this->vueComponents;
    }

    /**
     * Get or create Vue component registry instance
     */
    protected function getVueComponentRegistry(): ComponentRegistry
    {
        if (! $this->vueComponentRegistry) {
            $this->vueComponentRegistry = app(ComponentRegistry::class);
        }

        return $this->vueComponentRegistry;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;
}

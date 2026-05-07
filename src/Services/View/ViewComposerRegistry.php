<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\View;

use Illuminate\Support\Facades\View;
use Simtabi\Laranail\PackageTools\Contracts\RegistryInterface;

/**
 * ViewComposerRegistry - View composer registration
 *
 * Manages view composer registration for data injection
 */
class ViewComposerRegistry implements RegistryInterface
{
    protected array $composers = [];

    protected array $sharedData = [];

    /**
     * Register a view composer
     *
     * @param array|string $views View name(s)
     * @param string|callable $composer Composer class or callable
     */
    public function registerComposer(array|string $views, string|callable $composer): void
    {
        $views = is_array($views) ? $views : [$views];

        View::composer($views, $composer);

        foreach ($views as $view) {
            if (! isset($this->composers[$view])) {
                $this->composers[$view] = [];
            }
            $this->composers[$view][] = $composer;
        }
    }

    /**
     * Register a view creator
     *
     * @param array|string $views View name(s)
     * @param string|callable $creator Creator class or callable
     */
    public function registerCreator(array|string $views, string|callable $creator): void
    {
        $views = is_array($views) ? $views : [$views];
        View::creator($views, $creator);
    }

    /**
     * Share data with all views
     *
     * @param string $key Data key
     * @param mixed $value Data value
     */
    public function share(string $key, mixed $value): void
    {
        View::share($key, $value);
        $this->sharedData[$key] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function register(string $key, mixed $value): void
    {
        $this->registerComposer($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function getRegistered(): array
    {
        return [
            'composers' => $this->composers,
            'shared' => $this->sharedData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset($this->composers[$key]) || isset($this->sharedData[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->composers[$key] ?? $this->sharedData[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(string $key): void
    {
        unset($this->composers[$key], $this->sharedData[$key]);
    }
}

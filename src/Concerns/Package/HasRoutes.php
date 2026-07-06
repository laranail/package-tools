<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasRoutes
{
    /** @var list<string> */
    public array $routeFileNames = [];

    /** @var list<array{key: string, files: list<string>, default: bool}> */
    public array $conditionalRouteFileNames = [];

    public function hasRoute(string $routeFileName): static
    {
        $this->routeFileNames[] = $routeFileName;

        return $this;
    }

    /**
     * @param string|array<int, string> ...$routeFileNames
     */
    public function hasRoutes(...$routeFileNames): static
    {
        /** @var list<string> $flattened */
        $flattened = collect($routeFileNames)->flatten()->toArray();
        $this->routeFileNames = array_merge(
            $this->routeFileNames,
            $flattened
        );

        return $this;
    }

    /**
     * route files loaded only when config($configKey, $default) is truthy,
     * evaluated at boot.
     *
     * @param string|array<int, string> $routeFileNames
     */
    public function hasRoutesWhen(string $configKey, string|array $routeFileNames, bool $default = false): static
    {
        $this->conditionalRouteFileNames[] = [
            'key' => $configKey,
            'files' => array_values((array) $routeFileNames),
            'default' => $default,
        ];

        return $this;
    }
}

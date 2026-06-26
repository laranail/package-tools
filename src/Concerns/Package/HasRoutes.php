<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasRoutes
{
    /** @var list<string> */
    public array $routeFileNames = [];

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
}

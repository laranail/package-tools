<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasBladeComponents
{
    /** @var array<string, string> Map of component name => prefix */
    public array $viewComponents = [];

    public function hasViewComponent(string $prefix, string $viewComponentName): static
    {
        $this->viewComponents[$viewComponentName] = $prefix;

        return $this;
    }

    /**
     * @param string ...$viewComponentNames
     */
    public function hasViewComponents(string $prefix, ...$viewComponentNames): static
    {
        foreach ($viewComponentNames as $componentName) {
            $this->viewComponents[$componentName] = $prefix;
        }

        return $this;
    }
}

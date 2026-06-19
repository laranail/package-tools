<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

trait HasViewSharedData
{
    /** @var array<string, mixed> Map of name => shared value */
    public array $sharedViewData = [];

    /**
     * @param mixed $value
     */
    public function sharesDataWithAllViews(string $name, $value): static
    {
        $this->sharedViewData[$name] = $value;

        return $this;
    }
}

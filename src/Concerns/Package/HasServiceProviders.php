<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

trait HasServiceProviders
{
    public ?string $publishableProviderName = null;

    public function publishesServiceProvider(string $providerName): static
    {
        $this->publishableProviderName = $providerName;

        return $this;
    }
}

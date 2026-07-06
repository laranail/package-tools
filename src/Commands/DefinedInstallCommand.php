<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Override;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Support\Definitions\InstallCommandDefinition;

/**
 * The install command built from an InstallCommandDefinition: steps run in
 * declaration order — built-ins and custom steps interleave freely, unlike
 * the legacy fixed pipeline.
 */
final class DefinedInstallCommand extends InstallCommand
{
    public function __construct(
        Package $package,
        private readonly InstallCommandDefinition $definition,
    ) {
        parent::__construct(
            $package,
            $definition->signature(),
            $definition->isHidden(),
        );
    }

    #[Override]
    public function handle(): int
    {
        // steps are deliberately not wrapped in try/catch: an install step
        // that throws should abort the run loudly (artisan renders the
        // exception and exits non-zero) rather than half-install quietly —
        // same contract as the legacy fixed pipeline
        foreach ($this->definition->steps() as $step) {
            ($step['run'])($this);
        }

        $this->info("{$this->package->shortName()} has been installed!");

        return self::SUCCESS;
    }
}

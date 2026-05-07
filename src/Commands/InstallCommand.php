<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Commands;

use Illuminate\Console\Command;
use Simtabi\Laranail\PackageTools\Commands\Concerns\AskToRunMigrations;
use Simtabi\Laranail\PackageTools\Commands\Concerns\AskToStarRepoOnGitHub;
use Simtabi\Laranail\PackageTools\Commands\Concerns\PublishesResources;
use Simtabi\Laranail\PackageTools\Commands\Concerns\SupportsServiceProviderInApp;
use Simtabi\Laranail\PackageTools\Commands\Concerns\SupportsStartWithEndWith;
use Simtabi\Laranail\PackageTools\Package;

class InstallCommand extends Command
{
    use AskToRunMigrations;
    use AskToStarRepoOnGitHub;
    use PublishesResources;
    use SupportsServiceProviderInApp;
    use SupportsStartWithEndWith;

    protected Package $package;

    /**
     * Create a new install command instance
     *
     * @param Package $package The package to install
     */
    public function __construct(Package $package)
    {
        $this->signature = $package->shortName() . ':install';

        $this->description = 'Install ' . $package->name;

        $this->package = $package;

        // Hidden from `php artisan list` by default — the per-package signature
        // (`<shortName>:install`) is intentionally low-discovery; surface it
        // through your README's install instructions instead. Override
        // `$hidden = false` in a subclass when you do want it listed.
        $this->hidden = true;

        parent::__construct();
    }

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $this
            ->processStartWith()
            ->processPublishes()
            ->processAskToRunMigrations()
            ->processCopyServiceProviderInApp()
            ->processStarRepo()
            ->processEndWith();

        $this->info("{$this->package->shortName()} has been installed!");

        return self::SUCCESS;
    }
}

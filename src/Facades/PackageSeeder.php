<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederBuilder;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederManager;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;

/**
 * @method static SeederManager autoSeed(string $key, array $seeders, ?string $namespace = null, array $options = [])
 * @method static SeederBuilder seeders()
 * @method static SeederExecutionStats run()
 * @method static SeederRegistry registry()
 *
 * @see SeederManager
 */
final class PackageSeeder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SeederManager::class;
    }
}

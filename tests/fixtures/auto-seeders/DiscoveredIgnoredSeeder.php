<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\AutoSeeders;

use Illuminate\Database\Seeder;
use Simtabi\Laranail\Package\Tools\Tests\Feature\SeederRunLedger;

final class DiscoveredIgnoredSeeder extends Seeder
{
    public function run(): void
    {
        SeederRunLedger::record(self::class);
    }
}

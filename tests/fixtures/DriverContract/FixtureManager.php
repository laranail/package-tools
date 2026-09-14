<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\DriverContract;

use Illuminate\Support\Manager;

/** A stand-in Manager for exercising AssertsDriverContract. Ships two drivers, one hyphenated. */
class FixtureManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'alpha';
    }

    protected function createAlphaDriver(): string
    {
        return 'alpha-driver';
    }

    protected function createLemonSqueezyDriver(): string
    {
        return 'lemon-driver';
    }
}

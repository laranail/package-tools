<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Fixtures\Facade\Contracts;

use Simtabi\Laranail\PackageTools\Attributes\AsFacade;

#[AsFacade(alias: 'Counter', accessor: 'counter.service')]
interface CounterContract
{
    public function increment(int $by = 1): int;
}

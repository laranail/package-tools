<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\Facade\Contracts;

use Simtabi\Laranail\Package\Tools\Attributes\AsFacade;

#[AsFacade(alias: 'Counter', accessor: 'counter.service')]
interface CounterContract
{
    public function increment(int $by = 1): int;
}

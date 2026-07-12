<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\Facade\Contracts;

use Simtabi\Laranail\Package\Tools\Attributes\AsFacade;

#[AsFacade(alias: 'Greeter')]
interface GreeterContract
{
    public function greet(string $name, ?string $title = null): string;

    public function shout(string $message, int $times = 1): bool;

    public function whisper(): void;
}

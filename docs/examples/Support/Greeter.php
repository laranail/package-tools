<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: the concrete behind GreeterContract.
|------------------------------------------------------------------------------
| Bound to the contract in HelloPackageServiceProvider::packageRegistered().
| The generated "Greeter" facade (see Contracts/GreeterContract.php) resolves
| this implementation.
*/

namespace Acme\Hello\Support;

use Acme\Hello\Contracts\GreeterContract;

final class Greeter implements GreeterContract
{
    public function greet(string $name): string
    {
        $template = config('hello.greeting', 'Hello, :name!');

        return str_replace(':name', $name, (string) $template);
    }
}

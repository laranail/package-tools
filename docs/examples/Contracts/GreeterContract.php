<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a contract marked for facade generation.
|------------------------------------------------------------------------------
| #[AsFacade] is consumed by the `php artisan laranail::package-tools.ide-helper`
| command (not by ->discoversWithAttributes()). The generator emits a
| Facade-extending class aliased "Greeter" whose accessor resolves this
| contract from the container — so calls like Greeter::greet('world') get
| full IDE autocompletion.
|
| `alias` is the facade short name; `accessor` is the container key the facade
| proxies to (defaults to the contract's FQCN when omitted).
*/

namespace Acme\Hello\Contracts;

use Simtabi\Laranail\Package\Tools\Attributes\AsFacade;

#[AsFacade(alias: 'Greeter', accessor: GreeterContract::class)]
interface GreeterContract
{
    public function greet(string $name): string;
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\Listeners;

/**
 * Fixture listener skipped by discovery: its handle() parameter is untyped.
 */
final class NoTypeHintListener
{
    public function handle($event): void {}
}

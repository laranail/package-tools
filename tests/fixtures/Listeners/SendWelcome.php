<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\Listeners;

use Simtabi\Laranail\Package\Tools\Tests\Fixtures\Events\UserRegistered;

/**
 * Fixture listener discovered via its typed handle() parameter.
 */
final class SendWelcome
{
    public function handle(UserRegistered $event): void {}
}

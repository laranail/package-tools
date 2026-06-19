<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Support\Concerns;

use Simtabi\Laranail\PackageTools\Support\Concerns\HasErrorStorage;
use Simtabi\Laranail\PackageTools\Support\ErrorStorage\Contracts\ErrorStorageServiceInterface;
use Simtabi\Laranail\PackageTools\Support\ErrorStorage\ErrorStorageService;
use Simtabi\Laranail\PackageTools\Tests\TestCase;

final class HasErrorStorageTest extends TestCase
{
    public function test_trait_proxies_through_container_bound_service(): void
    {
        $this->app->bind(ErrorStorageServiceInterface::class, fn (): ErrorStorageService => ErrorStorageService::create());

        $host = new class
        {
            use HasErrorStorage;
        };

        self::assertFalse($host->hasErrors());

        $host->addError('field', 'first message');

        self::assertTrue($host->hasErrors());
        self::assertSame(1, $host->getErrorCount());
        self::assertSame(['first message'], $host->getErrors('field'));
        self::assertSame('first message', $host->getFirstError());

        $host->clearErrors();

        self::assertFalse($host->hasErrors());
    }

    public function test_trait_shares_the_bound_service_instance_across_calls(): void
    {
        // Container binding is shared, so two host objects see the same bag —
        // intentional for the package-install-command use case where one
        // command emits errors that a later boot hook reads back.
        $shared = ErrorStorageService::create();
        $this->app->instance(ErrorStorageServiceInterface::class, $shared);

        $a = new class
        {
            use HasErrorStorage;
        };
        $b = new class
        {
            use HasErrorStorage;
        };

        $a->addError('foo', 'from a');

        self::assertTrue($b->hasErrors(), 'Trait must read the same bound instance.');
        self::assertSame(['from a'], $b->getErrors('foo'));
    }
}

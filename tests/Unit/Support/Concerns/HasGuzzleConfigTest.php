<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support\Concerns;

use Simtabi\Laranail\Package\Tools\Services\Http\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Package\Tools\Services\Http\HttpConfigurationService;
use Simtabi\Laranail\Package\Tools\Support\Concerns\HasGuzzleConfig;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class HasGuzzleConfigTest extends TestCase
{
    public function test_trait_resolves_the_bound_http_config_service(): void
    {
        $this->app->singleton(
            HttpConfigurationServiceInterface::class,
            HttpConfigurationService::class,
        );

        // Anonymous host exposes the protected `httpConfig()` accessor
        // for assertion.
        $host = new class
        {
            use HasGuzzleConfig;

            public function expose(): HttpConfigurationServiceInterface
            {
                return $this->httpConfig();
            }
        };

        self::assertInstanceOf(
            HttpConfigurationServiceInterface::class,
            $host->expose(),
        );
    }

    public function test_trait_returns_the_same_singleton_across_calls(): void
    {
        $this->app->singleton(
            HttpConfigurationServiceInterface::class,
            HttpConfigurationService::class,
        );

        $host = new class
        {
            use HasGuzzleConfig;

            public function expose(): HttpConfigurationServiceInterface
            {
                return $this->httpConfig();
            }
        };

        self::assertSame($host->expose(), $host->expose());
    }
}

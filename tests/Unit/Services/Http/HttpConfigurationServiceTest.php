<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Services\Http\HttpConfigurationService;

final class HttpConfigurationServiceTest extends TestCase
{
    public function test_defaults_when_no_env_overrides(): void
    {
        $svc = new HttpConfigurationService(
            persistConnection: true,
            requestTimeout: 60,
            maxRetries: 10,
            cacheTtl: 10,
        );

        $this->assertTrue($svc->isPersistConnection());
        $this->assertSame(60, $svc->getRequestTimeout());
        $this->assertSame(10, $svc->getMaxRetries());
        $this->assertSame(10, $svc->getCacheTtl());
        $this->assertNull($svc->getBaseUri());
        $this->assertNull($svc->getProxy());
    }

    public function test_to_guzzle_config_yields_vendor_neutral_array(): void
    {
        $svc = (new HttpConfigurationService(
            persistConnection: false,
            requestTimeout: 30,
            maxRetries: 3,
            cacheTtl: 0,
        ))
            ->setBaseUri('https://api.example.com')
            ->setProxy('tcp://proxy.local:3128');

        $config = $svc->toGuzzleConfig();

        $this->assertSame([
            'persist' => false,
            'timeout' => 30,
            'retry' => ['max' => 3],
            'cache_ttl' => 0,
            'base_uri' => 'https://api.example.com',
            'proxy' => 'tcp://proxy.local:3128',
        ], $config);
    }

    public function test_omits_empty_optional_keys(): void
    {
        $svc = new HttpConfigurationService(
            persistConnection: true,
            requestTimeout: 1,
            maxRetries: 0,
            cacheTtl: 0,
        );

        $config = $svc->toGuzzleConfig();

        $this->assertArrayNotHasKey('base_uri', $config);
        $this->assertArrayNotHasKey('proxy', $config);
    }

    public function test_negative_timeout_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new HttpConfigurationService(persistConnection: true, requestTimeout: 1, maxRetries: 0, cacheTtl: 0))
            ->setRequestTimeout(-1);
    }

    public function test_negative_retries_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new HttpConfigurationService(persistConnection: true, requestTimeout: 1, maxRetries: 0, cacheTtl: 0))
            ->setMaxRetries(-1);
    }

    public function test_env_var_overrides(): void
    {
        $original = [
            'PKG_HTTP_REQUEST_TIMEOUT' => $_ENV['PKG_HTTP_REQUEST_TIMEOUT'] ?? null,
            'PKG_HTTP_MAX_RETRIES' => $_ENV['PKG_HTTP_MAX_RETRIES'] ?? null,
        ];
        try {
            $_ENV['PKG_HTTP_REQUEST_TIMEOUT'] = '15';
            $_ENV['PKG_HTTP_MAX_RETRIES'] = '5';

            $svc = new HttpConfigurationService;

            $this->assertSame(15, $svc->getRequestTimeout());
            $this->assertSame(5, $svc->getMaxRetries());
        } finally {
            foreach ($original as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }
    }
}

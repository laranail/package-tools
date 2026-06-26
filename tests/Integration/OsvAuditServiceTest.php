<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Services\Audit\OsvAuditService;

final class OsvAuditServiceTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRoot = __DIR__ . '/../fixtures/sbom';
    }

    public function test_audit_returns_clean_when_osv_finds_no_vulns(): void
    {
        Http::fake([
            'api.osv.dev/v1/querybatch' => Http::response([
                'results' => [
                    ['vulns' => []],
                    ['vulns' => []],
                    ['vulns' => []],
                ],
            ]),
        ]);

        $report = (new OsvAuditService($this->projectRoot))->audit();

        $this->assertSame(3, $report['scanned']);
        $this->assertSame(0, $report['vulnerable_count']);
        $this->assertSame([], $report['advisories']);
    }

    public function test_audit_aggregates_advisories_per_package(): void
    {
        Http::fake([
            'api.osv.dev/v1/querybatch' => Http::response([
                'results' => [
                    [
                        'vulns' => [[
                            'id' => 'GHSA-aaaa-bbbb-cccc',
                            'summary' => 'Sample advisory',
                            'severity' => [['type' => 'CVSS_V3', 'score' => '7.5']],
                        ]],
                    ],
                    ['vulns' => []],
                    ['vulns' => []],
                ],
            ]),
        ]);

        $report = (new OsvAuditService($this->projectRoot))->audit();

        $this->assertSame(3, $report['scanned']);
        $this->assertSame(1, $report['vulnerable_count']);
        $this->assertSame('illuminate/support', $report['advisories'][0]['package']);
        $this->assertSame('GHSA-aaaa-bbbb-cccc', $report['advisories'][0]['vulns'][0]['id']);
        $this->assertSame('Sample advisory', $report['advisories'][0]['vulns'][0]['summary']);
        $this->assertSame('7.5', $report['advisories'][0]['vulns'][0]['severity']);
        $this->assertSame(
            'https://api.osv.dev/v1/vulns/GHSA-aaaa-bbbb-cccc',
            $report['advisories'][0]['vulns'][0]['url'],
        );
    }

    public function test_audit_no_dev_skips_dev_packages(): void
    {
        Http::fake([
            'api.osv.dev/v1/querybatch' => Http::response([
                'results' => [
                    ['vulns' => []],
                    ['vulns' => []],
                ],
            ]),
        ]);

        $report = (new OsvAuditService($this->projectRoot))->audit(includeDev: false);

        $this->assertSame(2, $report['scanned']);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return count($body['queries']) === 2;
        });
    }

    public function test_audit_throws_on_http_failure(): void
    {
        Http::fake([
            'api.osv.dev/v1/querybatch' => Http::response('', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OSV.dev request failed (HTTP 500)');

        (new OsvAuditService($this->projectRoot))->audit();
    }

    public function test_audit_throws_on_missing_lock(): void
    {
        $tmp = sys_get_temp_dir() . '/audit-no-lock-' . uniqid();
        mkdir($tmp);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('composer.lock not found');

        try {
            (new OsvAuditService($tmp))->audit();
        } finally {
            @rmdir($tmp);
        }
    }

    public function test_audit_strips_v_prefix_from_versions_in_query(): void
    {
        Http::fake([
            'api.osv.dev/v1/querybatch' => Http::response([
                'results' => [
                    ['vulns' => []],
                    ['vulns' => []],
                    ['vulns' => []],
                ],
            ]),
        ]);

        (new OsvAuditService($this->projectRoot))->audit();

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $versions = array_column($body['queries'], 'version');

            return in_array('13.0.0', $versions, true)
                && in_array('3.5.0', $versions, true)
                && ! in_array('v13.0.0', $versions, true);
        });
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Sbom;

use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Services\Sbom\SbomGenerator;

final class SbomGeneratorTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureRoot = __DIR__ . '/../../../fixtures/sbom';
    }

    public function test_generate_produces_cyclonedx_envelope(): void
    {
        $sbom = (new SbomGenerator($this->fixtureRoot))->generate();

        $this->assertSame('CycloneDX', $sbom['bomFormat']);
        $this->assertSame('1.5', $sbom['specVersion']);
        $this->assertSame(1, $sbom['version']);
        $this->assertMatchesRegularExpression(
            '/^urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $sbom['serialNumber'],
        );
    }

    public function test_metadata_has_timestamp_and_tool(): void
    {
        $sbom = (new SbomGenerator($this->fixtureRoot))->generate();

        $this->assertArrayHasKey('timestamp', $sbom['metadata']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $sbom['metadata']['timestamp'],
        );
        $this->assertSame('simtabi', $sbom['metadata']['tools'][0]['vendor']);
        $this->assertSame('laranail/package-tools', $sbom['metadata']['tools'][0]['name']);
    }

    public function test_root_component_describes_application(): void
    {
        $sbom = (new SbomGenerator($this->fixtureRoot))->generate();

        $root = $sbom['metadata']['component'];
        $this->assertSame('application', $root['type']);
        $this->assertSame('acme/sample-app', $root['name']);
        $this->assertStringStartsWith('pkg:composer/acme/sample-app@', $root['purl']);
    }

    public function test_components_include_packages_and_packages_dev(): void
    {
        $sbom = (new SbomGenerator($this->fixtureRoot))->generate();

        $names = array_column($sbom['components'], 'name');
        $this->assertContains('illuminate/support', $names);
        $this->assertContains('psr/log', $names);
        $this->assertContains('pestphp/pest', $names);
        $this->assertCount(3, $sbom['components']);
    }

    public function test_dev_packages_marked_with_optional_scope(): void
    {
        $sbom = (new SbomGenerator($this->fixtureRoot))->generate();

        $byName = [];
        foreach ($sbom['components'] as $c) {
            $byName[$c['name']] = $c;
        }

        $this->assertSame('optional', $byName['pestphp/pest']['scope']);
        $this->assertArrayNotHasKey('scope', $byName['illuminate/support']);
    }

    public function test_purl_strips_v_prefix(): void
    {
        $sbom = (new SbomGenerator($this->fixtureRoot))->generate();

        $byName = array_column($sbom['components'], null, 'name');
        $this->assertSame('pkg:composer/illuminate/support@13.0.0', $byName['illuminate/support']['purl']);
        $this->assertSame('13.0.0', $byName['illuminate/support']['version']);
    }

    public function test_licenses_emitted_as_cyclonedx_format(): void
    {
        $sbom = (new SbomGenerator($this->fixtureRoot))->generate();

        $byName = array_column($sbom['components'], null, 'name');
        $this->assertSame(
            [['license' => ['id' => 'MIT']]],
            $byName['illuminate/support']['licenses'],
        );
    }

    public function test_throws_when_composer_lock_missing(): void
    {
        $tmp = sys_get_temp_dir() . '/sbom-no-lock-' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/composer.json', '{"name":"x/y"}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('composer.lock not found');

        try {
            (new SbomGenerator($tmp))->generate();
        } finally {
            @unlink($tmp . '/composer.json');
            @rmdir($tmp);
        }
    }

    public function test_generate_to_file_writes_pretty_json(): void
    {
        $written = (new SbomGenerator($this->fixtureRoot))->generateToFile('sbom-out.json');

        try {
            $this->assertFileExists($written);
            $this->assertStringEndsWith('/fixtures/sbom/sbom-out.json', $written);

            $decoded = json_decode((string) file_get_contents($written), true);
            $this->assertSame('CycloneDX', $decoded['bomFormat']);
        } finally {
            @unlink($written);
        }
    }

    public function test_output_outside_project_root_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside project root');

        (new SbomGenerator($this->fixtureRoot))->generateToFile('../escape/sbom.json');
    }

    public function test_absolute_output_outside_project_root_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside project root');

        (new SbomGenerator($this->fixtureRoot))->generateToFile('/tmp/' . uniqid() . '.json');
    }
}

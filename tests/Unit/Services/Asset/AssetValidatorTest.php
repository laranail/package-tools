<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Asset;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\PackageTools\Services\Asset\AssetValidator;

final class AssetValidatorTest extends TestCase
{
    private AssetValidator $validator;

    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AssetValidator;
        $this->tmpRoot = sys_get_temp_dir() . '/laranail-asset-' . bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmpRoot);
        parent::tearDown();
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function test_rejects_non_string_input(): void
    {
        $this->assertContains('Asset path must be a string', $this->validator->validate(['x']));
    }

    public function test_reports_missing_asset(): void
    {
        $errors = $this->validator->validate($this->tmpRoot . '/missing.css');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Asset does not exist', $errors[0]);
    }

    public function test_valid_non_empty_file_passes(): void
    {
        $file = $this->tmpRoot . '/app.css';
        file_put_contents($file, 'body{}');

        $this->assertSame([], $this->validator->validate($file));
        $this->assertTrue($this->validator->isValid($file));
    }

    public function test_empty_file_is_flagged(): void
    {
        $file = $this->tmpRoot . '/empty.css';
        file_put_contents($file, '');

        $errors = $this->validator->validate($file);

        $this->assertContains("Asset file is empty: {$file}", $errors);
    }

    public function test_directory_with_files_passes(): void
    {
        $dir = $this->tmpRoot . '/assets';
        mkdir($dir, 0o755, true);
        file_put_contents($dir . '/a.js', 'console.log(1)');

        $this->assertSame([], $this->validator->validate($dir));
    }

    public function test_empty_directory_is_flagged(): void
    {
        $dir = $this->tmpRoot . '/empty-dir';
        mkdir($dir, 0o755, true);

        $errors = $this->validator->validate($dir);

        $this->assertContains("Asset directory is empty: {$dir}", $errors);
    }
}

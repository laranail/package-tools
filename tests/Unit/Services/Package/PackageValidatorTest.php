<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Package;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\PackageTools\Services\Package\PackageValidator;

final class PackageValidatorTest extends TestCase
{
    private PackageValidator $validator;

    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PackageValidator;
        $this->tmpRoot = sys_get_temp_dir() . '/laranail-pkgvalidator-' . bin2hex(random_bytes(4));
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

    private function makeValidPackage(): string
    {
        $path = $this->tmpRoot . '/pkg';
        if (! is_dir($path . '/src')) {
            mkdir($path . '/src', 0o755, true);
        }
        file_put_contents($path . '/composer.json', '{"name":"acme/pkg"}');

        return $path;
    }

    public function test_validate_name_accepts_vendor_package_format(): void
    {
        $this->assertTrue($this->validator->validateName('acme/widget'));
        $this->assertTrue($this->validator->validateName('a-b/c-d'));
    }

    public function test_validate_name_rejects_invalid_format(): void
    {
        $this->assertFalse($this->validator->validateName('NoSlash'));
        $this->assertFalse($this->validator->validateName('Acme/Widget'));
        $this->assertFalse($this->validator->validateName('acme/widget/extra'));
    }

    public function test_validate_namespace_accepts_psr4(): void
    {
        $this->assertTrue($this->validator->validateNamespace('Acme\\Widget'));
        $this->assertTrue($this->validator->validateNamespace('Acme'));
    }

    public function test_validate_namespace_rejects_invalid(): void
    {
        $this->assertFalse($this->validator->validateNamespace('acme\\widget'));
        $this->assertFalse($this->validator->validateNamespace('Acme\\1Widget'));
    }

    public function test_validate_structure_true_for_complete_package(): void
    {
        $this->assertTrue($this->validator->validateStructure($this->makeValidPackage()));
    }

    public function test_validate_structure_false_when_missing_pieces(): void
    {
        $this->assertFalse($this->validator->validateStructure($this->tmpRoot . '/empty'));
    }

    public function test_validate_string_path_reports_missing_files(): void
    {
        $errors = $this->validator->validate($this->tmpRoot . '/empty');

        $this->assertContains('Required file missing: composer.json', $errors);
        $this->assertContains('Recommended directory missing: src', $errors);
    }

    public function test_validate_empty_path_string(): void
    {
        $this->assertContains('Path cannot be empty', $this->validator->validate(''));
    }

    public function test_validate_valid_path_string_is_clean(): void
    {
        $this->assertSame([], $this->validator->validate($this->makeValidPackage()));
        $this->assertTrue($this->validator->isValid($this->makeValidPackage()));
    }

    public function test_validate_package_data_array_happy_path(): void
    {
        $errors = $this->validator->validate([
            'name' => 'acme/widget',
            'path' => $this->makeValidPackage(),
            'namespace' => 'Acme\\Widget',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_validate_package_data_array_reports_rule_failures(): void
    {
        $errors = $this->validator->validate([
            'name' => 'INVALID NAME',
            'path' => '',
            'namespace' => '',
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_validate_rejects_non_string_non_array(): void
    {
        $errors = $this->validator->validate(42);

        $this->assertContains('Invalid input type. Expected string (path) or array (package data)', $errors);
    }
}

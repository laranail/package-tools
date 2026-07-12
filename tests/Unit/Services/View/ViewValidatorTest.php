<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\View;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\View\ViewValidator;

final class ViewValidatorTest extends TestCase
{
    private ViewValidator $validator;

    private string $viewDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ViewValidator;
        $this->viewDir = sys_get_temp_dir() . '/laranail-views-' . bin2hex(random_bytes(4));
        mkdir($this->viewDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        @rmdir($this->viewDir);
        parent::tearDown();
    }

    public function test_existing_directory_passes(): void
    {
        $this->assertSame([], $this->validator->validate($this->viewDir));
        $this->assertTrue($this->validator->isValid($this->viewDir));
    }

    public function test_empty_path_is_rejected(): void
    {
        $this->assertContains('View path cannot be empty', $this->validator->validate(''));
        $this->assertContains('View path cannot be empty', $this->validator->validate('0'));
    }

    public function test_missing_directory_is_rejected(): void
    {
        $errors = $this->validator->validate($this->viewDir . '/nope');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('View directory does not exist', $errors[0]);
    }

    public function test_validate_path_helper_returns_bool(): void
    {
        $this->assertTrue($this->validator->validatePath($this->viewDir));
        $this->assertFalse($this->validator->validatePath($this->viewDir . '/missing'));
    }

    public function test_validate_namespace_accepts_valid_format(): void
    {
        $this->assertTrue($this->validator->validateNamespace('admin-panel_v2/sub'));
    }

    public function test_validate_namespace_rejects_invalid_chars(): void
    {
        $this->assertFalse($this->validator->validateNamespace('bad namespace!'));
    }

    public function test_view_data_array_happy_path(): void
    {
        $errors = $this->validator->validate([
            'path' => $this->viewDir,
            'namespace' => 'pkg',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_view_data_array_reports_missing_path_directory(): void
    {
        $errors = $this->validator->validate([
            'path' => $this->viewDir . '/missing',
        ]);

        $this->assertContains("View path does not exist: {$this->viewDir}/missing", $errors);
    }

    public function test_view_data_array_reports_invalid_namespace(): void
    {
        $errors = $this->validator->validate([
            'path' => $this->viewDir,
            'namespace' => 'bad namespace',
        ]);

        $this->assertContains('Invalid namespace format: bad namespace', $errors);
    }

    public function test_view_data_array_requires_path(): void
    {
        $errors = $this->validator->validate(['namespace' => 'pkg']);

        $this->assertNotEmpty($errors);
    }

    public function test_rejects_non_string_non_array(): void
    {
        $this->assertContains(
            'Invalid input type. Expected string (path) or array (view data)',
            $this->validator->validate(99),
        );
    }
}

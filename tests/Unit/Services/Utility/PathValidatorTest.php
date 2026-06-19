<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Utility;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Services\Utility\PathValidator;

final class PathValidatorTest extends TestCase
{
    private PathValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PathValidator;
    }

    public function test_validate_accepts_simple_relative_path(): void
    {
        $this->assertSame([], $this->validator->validate('config/app.php'));
        $this->assertTrue($this->validator->isValid('config/app.php'));
    }

    public function test_validate_rejects_empty_path(): void
    {
        $errors = $this->validator->validate('');

        $this->assertContains('Path cannot be empty', $errors);
    }

    public function test_validate_rejects_zero_string_path(): void
    {
        $this->assertContains('Path cannot be empty', $this->validator->validate('0'));
    }

    public function test_validate_rejects_directory_traversal(): void
    {
        $errors = $this->validator->validate('../etc/passwd');

        $this->assertContains('Path contains directory traversal sequences', $errors);
        $this->assertFalse($this->validator->isValid('foo/../bar'));
    }

    public function test_validate_rejects_null_bytes(): void
    {
        $errors = $this->validator->validate("foo\0bar");

        $this->assertContains('Path contains null bytes', $errors);
    }

    public function test_validate_array_requires_path_key(): void
    {
        $this->assertContains('Path is required', $this->validator->validate(['notpath' => 'x']));
    }

    public function test_validate_array_uses_path_key(): void
    {
        $this->assertSame([], $this->validator->validate(['path' => 'src/Foo.php']));
    }

    public function test_validate_rejects_non_string_non_array(): void
    {
        $errors = $this->validator->validate(123);

        $this->assertContains('Invalid input type. Expected string (path) or array (path data)', $errors);
    }

    public function test_validate_path_helper_returns_bool(): void
    {
        $this->assertTrue($this->validator->validatePath('safe/path'));
        $this->assertFalse($this->validator->validatePath('../escape'));
    }

    public function test_cross_platform_flags_windows_invalid_characters(): void
    {
        $result = $this->validator->validateCrossPlatform('foo<bar>.txt');

        $this->assertFalse($result['valid']);
        $this->assertContains('Path contains characters invalid on Windows', $result['issues']);
    }

    public function test_cross_platform_flags_reserved_windows_name(): void
    {
        $result = $this->validator->validateCrossPlatform('some/dir/CON');

        $this->assertFalse($result['valid']);
        $this->assertContains('Path uses reserved name on Windows', $result['issues']);
    }

    public function test_cross_platform_warns_on_trailing_spaces(): void
    {
        $result = $this->validator->validateCrossPlatform('foo/bar ');

        $this->assertTrue($result['valid']);
        $this->assertContains('Path has trailing spaces', $result['warnings']);
    }

    public function test_cross_platform_clean_path_is_valid(): void
    {
        $result = $this->validator->validateCrossPlatform('config/app.php');

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['issues']);
    }

    public function test_sanitize_strips_null_bytes_and_collapses_separators(): void
    {
        $sep = DIRECTORY_SEPARATOR;
        $sanitized = $this->validator->sanitize("foo\0//bar");

        $this->assertStringNotContainsString("\0", $sanitized);
        $this->assertSame("foo{$sep}bar", $sanitized);
    }
}

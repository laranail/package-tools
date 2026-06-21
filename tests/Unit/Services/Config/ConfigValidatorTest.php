<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Config;

use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigValidator;

final class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;

    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConfigValidator;
        $this->tmpRoot = sys_get_temp_dir() . '/laranail-cfgvalidator-' . bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpRoot . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpRoot);
        parent::tearDown();
    }

    public function test_validate_reports_missing_file(): void
    {
        $errors = $this->validator->validate($this->tmpRoot . '/missing.php');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Configuration file not found', $errors[0]);
    }

    public function test_validate_accepts_valid_config_file(): void
    {
        $path = $this->tmpRoot . '/good.php';
        file_put_contents($path, "<?php return ['key' => 'value'];");

        $this->assertSame([], $this->validator->validate($path));
        $this->assertTrue($this->validator->isValid($path));
    }

    public function test_validate_rejects_file_not_returning_array(): void
    {
        $path = $this->tmpRoot . '/scalar.php';
        file_put_contents($path, '<?php return 42;');

        $errors = $this->validator->validate($path);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('must return an array', $errors[0]);
    }

    public function test_validate_reports_load_error_for_invalid_php(): void
    {
        $path = $this->tmpRoot . '/broken.php';
        file_put_contents($path, '<?php throw new \\RuntimeException("boom");');

        $errors = $this->validator->validate($path);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Error loading configuration file', $errors[0]);
        $this->assertStringContainsString('boom', $errors[0]);
    }

    public function test_validate_accepts_config_array(): void
    {
        $this->assertSame([], $this->validator->validate(['app' => ['name' => 'x']]));
        $this->assertTrue($this->validator->isValid(['foo' => 'bar']));
    }

    public function test_validate_rejects_invalid_input_type(): void
    {
        $errors = $this->validator->validate(123);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Invalid input type', $errors[0]);
    }
}

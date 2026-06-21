<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Facade;

use Orchestra\Testbench\TestCase;
use ReflectionClass;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Services\Facade\FacadeAutoGenerator;

final class FacadeAutoGeneratorTest extends TestCase
{
    private string $sourceDir;

    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourceDir = __DIR__ . '/../../../fixtures/facade';
        $this->outputDir = sys_get_temp_dir() . '/laranail-facade-' . uniqid();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->outputDir)) {
            foreach (glob($this->outputDir . '/*.php') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->outputDir);
        }
    }

    public function test_generator_emits_one_facade_per_contract(): void
    {
        $written = $this->generate();

        $aliases = array_column($written, 'alias');
        $this->assertContains('Greeter', $aliases);
        $this->assertContains('Counter', $aliases);
        $this->assertCount(2, $written);
    }

    public function test_facade_class_extends_laravel_facade(): void
    {
        $this->generate();
        $code = file_get_contents($this->outputDir . '/Greeter.php');

        $this->assertStringContainsString('namespace App\\Facades;', $code);
        $this->assertStringContainsString('use Illuminate\\Support\\Facades\\Facade;', $code);
        $this->assertStringContainsString('final class Greeter extends Facade', $code);
    }

    public function test_default_accessor_uses_contract_class_string(): void
    {
        $this->generate();
        $code = file_get_contents($this->outputDir . '/Greeter.php');

        $this->assertStringContainsString(
            'return \\Simtabi\\Laranail\\Package\\Tools\\Tests\\Fixtures\\Facade\\Contracts\\GreeterContract::class;',
            $code,
        );
    }

    public function test_explicit_accessor_overrides_default(): void
    {
        $this->generate();
        $code = file_get_contents($this->outputDir . '/Counter.php');

        $this->assertStringContainsString("return 'counter.service';", $code);
    }

    public function test_invalid_accessor_throws(): void
    {
        $generator = new FacadeAutoGenerator;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid AsFacade accessor');

        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('renderAccessorExpression');
        $method->invoke($generator, "evil; system('rm -rf /')");
    }

    public function test_method_doc_blocks_use_at_method_with_correct_signatures(): void
    {
        $this->generate();
        $code = file_get_contents($this->outputDir . '/Greeter.php');

        $this->assertStringContainsString(
            '@method static string greet(string $name, ?string $title = null)',
            $code,
        );
        $this->assertStringContainsString(
            '@method static bool shout(string $message, int $times = 1)',
            $code,
        );
        $this->assertStringContainsString(
            '@method static void whisper()',
            $code,
        );
    }

    public function test_generated_files_are_syntactically_valid_php(): void
    {
        $this->generate();

        foreach (glob($this->outputDir . '/*.php') ?: [] as $file) {
            $output = [];
            $exit = 0;
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exit);
            $this->assertSame(0, $exit, "Syntax error in {$file}: " . implode("\n", $output));
        }
    }

    /**
     * @return list<array{contract: string, facade: string, alias: string, file: string}>
     */
    private function generate(): array
    {
        return (new FacadeAutoGenerator)->generate(
            sourceDirectory: $this->sourceDir,
            sourceNamespace: 'Simtabi\\Laranail\\Package\\Tools\\Tests\\Fixtures\\Facade',
            outputDirectory: $this->outputDir,
            facadeNamespace: 'App\\Facades',
        );
    }
}

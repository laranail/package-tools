<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederPathDiscoverer;

final class SeederPathDiscovererTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/seeder-discover-' . uniqid();
        File::ensureDirectoryExists($this->sandbox);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        File::deleteDirectory($this->sandbox);
    }

    public function test_discover_returns_only_seeder_subclasses(): void
    {
        $this->writeSeeder('OneSeeder', 'Tests\\SeederFx');
        $this->writeNonSeeder('NotASeeder', 'Tests\\SeederFx');

        $found = (new SeederPathDiscoverer)->discover($this->sandbox);

        $this->assertContains('Tests\\SeederFx\\OneSeeder', $found);
        $this->assertNotContains('Tests\\SeederFx\\NotASeeder', $found);
    }

    public function test_discover_throws_for_nonexistent_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        (new SeederPathDiscoverer)->discover('/nonexistent/' . uniqid());
    }

    public function test_classes_in_extracts_namespaced_fqcns(): void
    {
        $file = $this->sandbox . '/HelloSeeder.php';
        File::put($file, "<?php\nnamespace Sample\\Demo;\nclass HelloSeeder { }\n");

        $classes = (new SeederPathDiscoverer)->classesIn($file);

        $this->assertSame(['Sample\\Demo\\HelloSeeder'], $classes);
    }

    public function test_classes_in_returns_empty_for_missing_file(): void
    {
        $this->assertSame([], (new SeederPathDiscoverer)->classesIn('/no/such/file.php'));
    }

    private function writeSeeder(string $name, string $namespace): void
    {
        $fqcn = $namespace . '\\' . $name;
        $parent = '\\' . Seeder::class;
        $file = $this->sandbox . "/{$name}.php";
        File::put($file, <<<PHP
<?php
namespace {$namespace};
class {$name} extends {$parent}
{
    public function run(): void {}
}
PHP);
        require_once $file;

        $this->assertTrue(class_exists($fqcn));
    }

    private function writeNonSeeder(string $name, string $namespace): void
    {
        $file = $this->sandbox . "/{$name}.php";
        File::put($file, <<<PHP
<?php
namespace {$namespace};
class {$name}
{
    public function run(): void {}
}
PHP);
        require_once $file;
    }
}

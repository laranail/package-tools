<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Discovery;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\PackageTools\Attributes\AsArtisanCommand;
use Simtabi\Laranail\PackageTools\Attributes\AsRoute;
use Simtabi\Laranail\PackageTools\Services\Discovery\AttributeDiscoverer;

final class AttributeDiscovererTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/laranail-attr-discovery-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
    }

    public function test_finds_classes_with_target_attribute(): void
    {
        $this->writeFile('Foo.php', <<<'PHP'
            <?php
            namespace Stub\Discovery;
            use Simtabi\Laranail\PackageTools\Attributes\AsArtisanCommand;

            #[AsArtisanCommand(signature: 'foo:run')]
            final class Foo {}
            PHP);

        $this->writeFile('Bar.php', <<<'PHP'
            <?php
            namespace Stub\Discovery;
            final class Bar {}
            PHP);

        $hits = iterator_to_array(
            (new AttributeDiscoverer)->discover(
                directory: $this->tmpDir,
                rootNamespace: 'Stub\\Discovery',
                attributeClass: AsArtisanCommand::class,
            ),
        );

        $this->assertCount(1, $hits);
        $this->assertSame('Stub\\Discovery\\Foo', $hits[0]['class']->getName());
        $this->assertSame('foo:run', $hits[0]['attributes'][0]->newInstance()->signature);
    }

    public function test_traverses_nested_directories(): void
    {
        mkdir($this->tmpDir . '/Sub/Nested', 0o755, true);
        $this->writeFile('Sub/Nested/Deep.php', <<<'PHP'
            <?php
            namespace Stub\Discovery\Sub\Nested;
            use Simtabi\Laranail\PackageTools\Attributes\AsArtisanCommand;

            #[AsArtisanCommand(signature: 'deep:run')]
            final class Deep {}
            PHP);

        $hits = iterator_to_array(
            (new AttributeDiscoverer)->discover(
                directory: $this->tmpDir,
                rootNamespace: 'Stub\\Discovery',
                attributeClass: AsArtisanCommand::class,
            ),
        );

        $this->assertCount(1, $hits);
        $this->assertSame('Stub\\Discovery\\Sub\\Nested\\Deep', $hits[0]['class']->getName());
    }

    public function test_handles_repeatable_attributes(): void
    {
        $this->writeFile('Multi.php', <<<'PHP'
            <?php
            namespace Stub\Discovery;
            use Simtabi\Laranail\PackageTools\Attributes\AsRoute;

            #[AsRoute(method: 'GET',  uri: '/a')]
            #[AsRoute(method: 'POST', uri: '/a', name: 'a.post')]
            final class Multi {}
            PHP);

        $hits = iterator_to_array(
            (new AttributeDiscoverer)->discover(
                directory: $this->tmpDir,
                rootNamespace: 'Stub\\Discovery',
                attributeClass: AsRoute::class,
            ),
        );

        $this->assertCount(1, $hits);
        $this->assertCount(2, $hits[0]['attributes']);
    }

    public function test_throws_on_missing_directory(): void
    {
        $this->expectException(RuntimeException::class);
        iterator_to_array(
            (new AttributeDiscoverer)->discover(
                directory: $this->tmpDir . '/does-not-exist',
                rootNamespace: 'Stub\\Discovery',
                attributeClass: AsArtisanCommand::class,
            ),
        );
    }

    private function writeFile(string $relative, string $contents): void
    {
        $path = $this->tmpDir . '/' . $relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }
        file_put_contents($path, $contents);
        // Trigger Composer to autoload the temp class:
        require $path;
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}

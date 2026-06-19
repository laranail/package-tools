<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Package;

use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\PackageTools\Services\Package\PackageAnalyzer;

final class PackageAnalyzerTest extends TestCase
{
    private PackageAnalyzer $analyzer;

    private string $pkg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new PackageAnalyzer;
        $this->pkg = sys_get_temp_dir() . '/laranail-analyzer-' . bin2hex(random_bytes(4));
        mkdir($this->pkg . '/src', 0o755, true);
        mkdir($this->pkg . '/tests', 0o755, true);

        file_put_contents($this->pkg . '/composer.json', json_encode([
            'name' => 'acme/widget',
            'type' => 'library',
            'license' => 'MIT',
            'require' => ['php' => '^8.3', 'illuminate/support' => '^11.0'],
            'require-dev' => ['pestphp/pest' => '^3.0'],
        ]));
        file_put_contents($this->pkg . '/README.md', '# Widget');
        file_put_contents($this->pkg . '/LICENSE', 'MIT');
        file_put_contents(
            $this->pkg . '/src/Widget.php',
            "<?php\nclass Widget {\n    public function run() {}\n    public function stop() {}\n}\n",
        );
        file_put_contents($this->pkg . '/src/view.blade.php', '<div></div>');
        file_put_contents($this->pkg . '/src/app.js', 'console.log(1);');
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->pkg);
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

    public function test_analyze_returns_all_sections(): void
    {
        $result = $this->analyzer->analyze($this->pkg);

        $this->assertArrayHasKey('structure', $result);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('composer', $result);
    }

    public function test_structure_flags_present_and_absent_dirs(): void
    {
        $structure = $this->analyzer->analyze($this->pkg)['structure'];

        $this->assertTrue($structure['has_src']);
        $this->assertTrue($structure['has_tests']);
        $this->assertTrue($structure['has_composer']);
        $this->assertTrue($structure['has_readme']);
        $this->assertTrue($structure['has_license']);
        $this->assertFalse($structure['has_config']);
        $this->assertFalse($structure['has_routes']);
    }

    public function test_metrics_count_classes_and_methods(): void
    {
        $metrics = $this->analyzer->analyze($this->pkg)['metrics'];

        $this->assertSame(1, $metrics['classes']);
        $this->assertSame(2, $metrics['methods']);
        $this->assertGreaterThan(0, $metrics['lines_of_code']);
    }

    public function test_metrics_zero_when_no_src(): void
    {
        $empty = sys_get_temp_dir() . '/laranail-analyzer-empty-' . bin2hex(random_bytes(4));
        mkdir($empty, 0o755, true);

        try {
            $metrics = $this->analyzer->analyze($empty)['metrics'];
            $this->assertSame(['lines_of_code' => 0, 'classes' => 0, 'methods' => 0], $metrics);
        } finally {
            @rmdir($empty);
        }
    }

    public function test_file_counts_by_extension(): void
    {
        $files = $this->analyzer->analyze($this->pkg)['files'];

        $this->assertSame(1, $files['js']);
        // *.blade.php is counted as blade (checked before the generic php branch),
        // so it does not inflate the php count: one Widget.php → php = 1.
        $this->assertSame(1, $files['blade']);
        $this->assertSame(1, $files['php']);
    }

    public function test_composer_section_extracts_metadata(): void
    {
        $composer = $this->analyzer->analyze($this->pkg)['composer'];

        $this->assertSame('acme/widget', $composer['name']);
        $this->assertSame('library', $composer['type']);
        $this->assertSame('MIT', $composer['license']);
        $this->assertSame(2, $composer['dependencies_count']);
        $this->assertSame(1, $composer['dev_dependencies_count']);
    }

    public function test_composer_section_empty_when_missing(): void
    {
        $empty = sys_get_temp_dir() . '/laranail-analyzer-nocomposer-' . bin2hex(random_bytes(4));
        mkdir($empty, 0o755, true);

        try {
            $this->assertSame([], $this->analyzer->analyze($empty)['composer']);
        } finally {
            @rmdir($empty);
        }
    }

    public function test_get_report_json_round_trips(): void
    {
        $findings = ['structure' => ['has_src' => true], 'metrics' => ['classes' => 1]];

        $json = $this->analyzer->getReport($findings, 'json');

        $this->assertSame($findings, json_decode($json, true));
    }

    public function test_get_report_text_flattens_nested_keys(): void
    {
        $text = $this->analyzer->getReport(['structure' => ['has_src' => true, 'has_tests' => false]], 'text');

        $this->assertStringContainsString('structure.has_src = true', $text);
        $this->assertStringContainsString('structure.has_tests = false', $text);
    }

    public function test_get_report_rejects_unknown_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->analyzer->getReport([], 'xml');
    }
}

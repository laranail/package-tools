<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Illuminate\Console\OutputStyle;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederConsoleFormatter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SeederConsoleFormatterTest extends TestCase
{
    protected function tearDown(): void
    {
        Capabilities::clearFake();
        parent::tearDown();
    }

    private function render(SeederConsoleFormatter $formatter, BufferedOutput $buffer): string
    {
        $formatter->setOutput(new OutputStyle(new ArrayInput([]), $buffer));
        $formatter->initializeSession();

        $formatter->displayGroupHeader('Acme\\Blog', 2);
        $formatter->displaySeederSuccess('Acme\\Blog\\PostSeeder', 0.012);
        $formatter->displaySeederError('Acme\\Blog\\TagSeeder', new RuntimeException('boom'), 0.003);
        $formatter->displaySeederSkipped('Acme\\Blog\\UserSeeder', 'already seeded');
        $formatter->writeInfo('informational message');
        $formatter->displaySummary();

        return $buffer->fetch();
    }

    public function test_it_implements_the_contract(): void
    {
        $this->assertInstanceOf(SeederConsoleFormatterInterface::class, new SeederConsoleFormatter);
    }

    public function test_it_tracks_statistics_across_a_session(): void
    {
        $formatter = new SeederConsoleFormatter;
        $formatter->initializeSession();

        // No OutputStyle set → silent, but statistics still accumulate.
        $formatter->displayGroupHeader('Acme\\Blog', 2);
        $formatter->displaySeederSuccess('Acme\\Blog\\PostSeeder', 0.012);
        $formatter->displaySeederError('Acme\\Blog\\TagSeeder', new RuntimeException('boom'), 0.003);

        $stats = $formatter->getStatistics();
        $this->assertSame(1, $stats['groups']);
        $this->assertSame(1, $stats['successful']);
        $this->assertSame(1, $stats['failed']);
        $this->assertGreaterThanOrEqual(0.0, $formatter->getSessionDuration());
    }

    public function test_reset_zeroes_statistics(): void
    {
        $formatter = new SeederConsoleFormatter;
        $formatter->displayGroupHeader('X', 1);
        $formatter->resetStatistics();

        $this->assertSame(
            ['groups' => 0, 'successful' => 0, 'failed' => 0, 'skipped' => 0],
            $formatter->getStatistics(),
        );
    }

    public function test_it_renders_meaningful_output_through_the_console_widgets(): void
    {
        Capabilities::fake(unicode: true);

        $output = $this->render(new SeederConsoleFormatter, new BufferedOutput);

        // Header carries the group name and the count + item label.
        $this->assertStringContainsString('Acme\\Blog', $output);
        $this->assertStringContainsString('2 seeders', $output);

        // Per-seeder lines carry the full de-suffixed names and status words.
        $this->assertStringContainsString('Post', $output);
        $this->assertStringContainsString('DONE', $output);
        $this->assertStringContainsString('Tag', $output);
        $this->assertStringContainsString('FAILED', $output);
        $this->assertStringContainsString('boom', $output);
        $this->assertStringContainsString('SKIPPED', $output);
        $this->assertStringContainsString('already seeded', $output);

        // Unicode glyphs + tree branches on a capable terminal.
        $this->assertStringContainsString('✓', $output);
        $this->assertStringContainsString('├─', $output);

        // Info badge + message.
        $this->assertStringContainsString('INFO', $output);
        $this->assertStringContainsString('informational message', $output);

        // Summary statistics + correctly-pluralised final status.
        $this->assertStringContainsString('Successful', $output);
        $this->assertStringContainsString('Failed', $output);
        $this->assertStringContainsString('Skipped', $output);
        $this->assertStringContainsString('Completed with 1 failure out of', $output);
    }

    public function test_it_degrades_to_ascii_glyphs_without_unicode(): void
    {
        Capabilities::fake(unicode: false);

        $output = $this->render(new SeederConsoleFormatter, new BufferedOutput);

        // ASCII fallbacks instead of Unicode glyphs/box-drawing.
        $this->assertStringContainsString('[OK]', $output);
        $this->assertStringContainsString('|-', $output);
        $this->assertStringNotContainsString('✓', $output);
        $this->assertStringNotContainsString('├─', $output);

        // Content still present.
        $this->assertStringContainsString('Post', $output);
        $this->assertStringContainsString('DONE', $output);
    }
}

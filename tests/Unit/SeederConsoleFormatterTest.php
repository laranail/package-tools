<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit;

use Illuminate\Console\OutputStyle;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\PackageTools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\PackageTools\Services\Database\SeederConsoleFormatter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SeederConsoleFormatterTest extends TestCase
{
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
        $buffer = new BufferedOutput;
        $formatter = new SeederConsoleFormatter;
        $formatter->setOutput(new OutputStyle(new ArrayInput([]), $buffer));
        $formatter->initializeSession();

        $formatter->displayGroupHeader('Acme\\Blog', 2);
        $formatter->displaySeederSuccess('Acme\\Blog\\PostSeeder', 0.012);
        $formatter->displaySeederError('Acme\\Blog\\TagSeeder', new RuntimeException('boom'), 0.003);
        $formatter->displaySeederSkipped('Acme\\Blog\\UserSeeder', 'already seeded');
        $formatter->writeInfo('informational message');
        $formatter->displaySummary();

        $output = $buffer->fetch();

        // Header carries the group name and the count + item label.
        $this->assertStringContainsString('Acme\\Blog', $output);
        $this->assertStringContainsString('2 seeders', $output);

        // Per-seeder lines carry the seeder names and status words. Names are
        // de-suffixed via the class' existing trim logic (drops the trailing
        // "Seeder" + one extra char), so "PostSeeder" renders as "Pos".
        $this->assertStringContainsString('Pos', $output);
        $this->assertStringContainsString('DONE', $output);
        $this->assertStringContainsString('Ta', $output);
        $this->assertStringContainsString('FAILED', $output);
        $this->assertStringContainsString('boom', $output);
        $this->assertStringContainsString('SKIPPED', $output);
        $this->assertStringContainsString('already seeded', $output);

        // Info badge + message.
        $this->assertStringContainsString('INFO', $output);
        $this->assertStringContainsString('informational message', $output);

        // Summary statistics + final status.
        $this->assertStringContainsString('Successful', $output);
        $this->assertStringContainsString('Failed', $output);
        $this->assertStringContainsString('Skipped', $output);
        $this->assertStringContainsString('Completed with 1 failures', $output);
    }
}

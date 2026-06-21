<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use Illuminate\Console\Command;
use Simtabi\Laranail\Package\Tools\Concerns\Commands\HasProgressBar;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class HasProgressBarTest extends TestCase
{
    public function test_with_progress_iterates_items_and_returns_results(): void
    {
        $host = $this->newCommandHost();
        $host->bindOutput(new BufferedOutput);

        $results = $host->withProgress(['a', 'b', 'c'], fn (string $item): string => strtoupper($item));

        self::assertSame(['A', 'B', 'C'], $results);
    }

    public function test_progress_lifecycle_can_be_driven_manually(): void
    {
        $host = $this->newCommandHost();
        $buffer = new BufferedOutput;
        $host->bindOutput($buffer);

        $host->startProgress(3);
        $host->advanceProgress();
        $host->setProgressMessage('working');
        $host->advanceProgress(2);
        $host->finishProgress('done');

        $output = $buffer->fetch();

        self::assertStringContainsString('done', $output);
    }

    public function test_clear_progress_short_circuits_when_no_bar_running(): void
    {
        $host = $this->newCommandHost();
        $host->bindOutput(new BufferedOutput);

        // No active bar; must not throw.
        $host->clearProgress();
        $host->advanceProgress();
        $host->setProgressMessage('ignored');

        $this->expectNotToPerformAssertions();
    }

    /**
     * Anonymous Command host so the trait has the `output` it expects.
     */
    private function newCommandHost(): Command
    {
        return new class extends Command
        {
            use HasProgressBar {
                // re-export protected so the test can drive lifecycle.
                startProgress as public;
                advanceProgress as public;
                setProgressMessage as public;
                finishProgress as public;
                clearProgress as public;
                withProgress as public;
            }

            public function bindOutput(BufferedOutput $buffer): void
            {
                $this->output = new SymfonyStyle(new StringInput(''), $buffer);
            }
        };
    }
}

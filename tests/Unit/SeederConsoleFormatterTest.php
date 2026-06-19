<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\PackageTools\Services\Database\Contracts\SeederConsoleFormatterInterface;
use Simtabi\Laranail\PackageTools\Services\Database\SeederConsoleFormatter;

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
}

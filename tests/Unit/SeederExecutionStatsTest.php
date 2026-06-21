<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\ValueObjects\SeederExecutionStats;

final class SeederExecutionStatsTest extends TestCase
{
    public function test_from_array_round_trips(): void
    {
        $stats = SeederExecutionStats::fromArray([
            'total' => 3,
            'success' => 2,
            'failed' => 1,
            'totalTime' => 1500.0,
            'errors' => [['class' => 'X', 'message' => 'boom']],
            'group' => 'core',
        ]);

        $this->assertSame(3, $stats->total);
        $this->assertSame(2, $stats->success);
        $this->assertSame(1, $stats->failed);
        $this->assertSame('core', $stats->group);
        $this->assertFalse($stats->isSuccessful());
        $this->assertTrue($stats->hasFailures());
        $this->assertSame(['boom'], $stats->getErrorMessages());
    }

    public function test_empty_and_rates(): void
    {
        $this->assertTrue(SeederExecutionStats::empty('g')->isEmpty());
        $this->assertSame(100.0, SeederExecutionStats::empty()->getSuccessRate());

        $stats = SeederExecutionStats::fromArray(['total' => 4, 'success' => 3, 'failed' => 1, 'totalTime' => 2000.0]);
        $this->assertSame(75.0, $stats->getSuccessRate());
        $this->assertSame('2.00s', $stats->getFormattedTotalTime());
    }

    public function test_merge(): void
    {
        $a = SeederExecutionStats::fromArray(['total' => 1, 'success' => 1, 'failed' => 0, 'totalTime' => 10.0]);
        $b = SeederExecutionStats::fromArray(['total' => 2, 'success' => 1, 'failed' => 1, 'totalTime' => 20.0]);

        $merged = $a->merge($b);

        $this->assertSame(3, $merged->total);
        $this->assertSame(2, $merged->success);
        $this->assertSame(1, $merged->failed);
        $this->assertSame(30.0, $merged->totalTime);
    }
}

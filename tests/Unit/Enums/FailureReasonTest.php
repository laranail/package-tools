<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Enums;

use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;

/**
 * FailureReason::fromThrowable is the single throwable → reason mapping;
 * queue timeout / max-attempt exhaustion is a TimedOut, everything else a
 * plain Failed.
 */
final class FailureReasonTest extends TestCase
{
    public function test_timeout_exceeded_maps_to_timed_out(): void
    {
        $this->assertSame(
            FailureReason::TimedOut,
            FailureReason::fromThrowable(new TimeoutExceededException('t')),
        );
    }

    public function test_max_attempts_exceeded_maps_to_timed_out(): void
    {
        $this->assertSame(
            FailureReason::TimedOut,
            FailureReason::fromThrowable(new MaxAttemptsExceededException('m')),
        );
    }

    public function test_any_other_throwable_maps_to_failed(): void
    {
        $this->assertSame(
            FailureReason::Failed,
            FailureReason::fromThrowable(new RuntimeException('boom')),
        );
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Timed out', FailureReason::TimedOut->label());
        $this->assertSame('Cancelled', FailureReason::Cancelled->label());
    }
}

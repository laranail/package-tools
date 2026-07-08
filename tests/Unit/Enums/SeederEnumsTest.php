<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\QueueConnection;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Enums\SeederRunStatus;

final class SeederEnumsTest extends TestCase
{
    #[Test]
    public function queue_connection_covers_laravels_built_in_connections(): void
    {
        $values = array_map(static fn (QueueConnection $c): string => $c->value, QueueConnection::cases());

        $this->assertSame(['sync', 'database', 'redis', 'sqs', 'beanstalkd', 'null'], $values);
        // PHP forbids an enum case named Null — the 'null' connection maps
        // to None. Guard the case↔value pairing explicitly.
        $this->assertSame(QueueConnection::None, QueueConnection::from('null'));
    }

    #[Test]
    public function execution_mode_round_trips(): void
    {
        foreach (SeederExecutionMode::cases() as $case) {
            $this->assertSame($case, SeederExecutionMode::from($case->value));
        }

        $this->assertSame(['inline', 'queued', 'scheduled'], array_column(SeederExecutionMode::cases(), 'value'));
    }

    #[Test]
    public function run_status_round_trips_and_try_from_tolerates_junk(): void
    {
        foreach (SeederRunStatus::cases() as $case) {
            $this->assertSame($case, SeederRunStatus::from($case->value));
        }

        $this->assertNull(SeederRunStatus::tryFrom('exploded'));
    }
}

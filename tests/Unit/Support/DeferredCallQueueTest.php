<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Enums\Environment;
use Simtabi\Laranail\Package\Tools\Support\DeferredCallQueue;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\CronBuilder;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\TimeOfDay;

/**
 * the deferred-call recorder: order-preserving record/replay (closures
 * interleaved), method_exists validation, and the replay-time argument
 * normalizer for typed values.
 */
final class DeferredCallQueueTest extends TestCase
{
    #[Test]
    public function it_starts_empty(): void
    {
        $queue = new DeferredCallQueue;

        $this->assertTrue($queue->isEmpty());
        $this->assertSame([], $queue->all());
    }

    #[Test]
    public function record_makes_the_queue_non_empty_and_all_exposes_the_calls(): void
    {
        $queue = new DeferredCallQueue;
        $queue->record('dailyAt', ['02:00']);

        $this->assertFalse($queue->isEmpty());
        $this->assertSame([['method' => 'dailyAt', 'args' => ['02:00']]], $queue->all());
    }

    #[Test]
    public function replay_preserves_recording_order_including_interleaved_closures(): void
    {
        $queue = new DeferredCallQueue;
        $target = new RecordingReplayTarget;

        $queue->record('dailyAt', ['02:00']);
        $queue->recordClosure(static function (RecordingReplayTarget $t): void {
            $t->calls[] = ['closure', []];
        });
        $queue->record('withoutOverlapping', [10]);

        $queue->replayOn($target);

        $this->assertSame([
            ['dailyAt', ['02:00']],
            ['closure', []],
            ['withoutOverlapping', [10]],
        ], $target->calls);
    }

    #[Test]
    public function closures_receive_the_replay_target(): void
    {
        $queue = new DeferredCallQueue;
        $target = new RecordingReplayTarget;
        $received = null;

        $queue->recordClosure(static function (object $t) use (&$received): void {
            $received = $t;
        });
        $queue->replayOn($target);

        $this->assertSame($target, $received);
    }

    #[Test]
    public function replaying_an_unknown_method_throws_and_names_it(): void
    {
        $queue = new DeferredCallQueue;
        $queue->record('notARealMethod', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('deferred call "notARealMethod" does not exist on ' . RecordingReplayTarget::class);

        $queue->replayOn(new RecordingReplayTarget);
    }

    #[Test]
    public function replay_normalizes_backed_enums_to_their_values(): void
    {
        $queue = new DeferredCallQueue;
        $target = new RecordingReplayTarget;

        $queue->record('environments', [Environment::Production]);
        $queue->replayOn($target);

        $this->assertSame([['environments', ['production']]], $target->calls);
    }

    #[Test]
    public function replay_normalizes_enums_inside_array_arguments(): void
    {
        $queue = new DeferredCallQueue;
        $target = new RecordingReplayTarget;

        // environments([Environment::Production, 'staging']) — the array
        // form must normalize its members like the variadic form does
        $queue->record('environments', [[Environment::Production, 'staging']]);
        $queue->replayOn($target);

        $this->assertSame([['environments', [['production', 'staging']]]], $target->calls);
    }

    #[Test]
    public function replay_normalizes_time_of_day_to_the_canonical_24_hour_string(): void
    {
        $queue = new DeferredCallQueue;
        $target = new RecordingReplayTarget;

        $queue->record('dailyAt', [TimeOfDay::pm(5, 30)]);
        $queue->replayOn($target);

        $this->assertSame([['dailyAt', ['17:30']]], $target->calls);
    }

    #[Test]
    public function replay_normalizes_cron_expressibles_to_expression_strings(): void
    {
        $queue = new DeferredCallQueue;
        $target = new RecordingReplayTarget;

        $queue->record('cron', [CronBuilder::make()->at('02:00')]);
        $queue->replayOn($target);

        $this->assertSame([['cron', ['0 2 * * *']]], $target->calls);
    }

    #[Test]
    public function replay_passes_plain_arguments_through_untouched(): void
    {
        $queue = new DeferredCallQueue;
        $target = new RecordingReplayTarget;

        $queue->record('withoutOverlapping', [1440]);
        $queue->replayOn($target);

        $this->assertSame([['withoutOverlapping', [1440]]], $target->calls);
    }
}

/**
 * replay target with real methods (replayOn validates via method_exists).
 */
final class RecordingReplayTarget
{
    /** @var list<array{0: string, 1: array<int, mixed>}> */
    public array $calls = [];

    public function dailyAt(mixed ...$args): void
    {
        $this->calls[] = ['dailyAt', $args];
    }

    public function withoutOverlapping(mixed ...$args): void
    {
        $this->calls[] = ['withoutOverlapping', $args];
    }

    public function environments(mixed ...$args): void
    {
        $this->calls[] = ['environments', $args];
    }

    public function cron(mixed ...$args): void
    {
        $this->calls[] = ['cron', $args];
    }
}

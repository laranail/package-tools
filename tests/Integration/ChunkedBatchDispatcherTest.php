<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use LogicException;
use RuntimeException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\PendingBatch;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Simtabi\Laranail\Package\Tools\Enums\SeederRunStatus;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRunTracker;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Database\ChunkedBatchDispatcher;

/**
 * A chunked workload runs one job per chunk, queued as a batch or inline, and
 * reports progress through SeederRunTracker. The queued tests use the real
 * `sync` connection with a real job_batches table rather than Bus::fake(), so
 * the batch callbacks are actually serialized -- the property that breaks when
 * a callback captures a non-serializable object.
 */
final class ChunkedBatchDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ChunkRecordingJob::$seen = [];

        Schema::create('job_batches', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    public function test_items_are_split_into_chunks_of_the_requested_size(): void
    {
        $chunks = ChunkedBatchDispatcher::make('t')->items(range(1, 7))->chunk(3)->chunks();

        self::assertSame([[1, 2, 3], [4, 5, 6], [7]], $chunks);
        self::assertSame([[1], [2]], ChunkedBatchDispatcher::make('t')->items([1, 2])->chunk(0)->chunks());
    }

    public function test_inline_runs_every_chunk_and_tracks_progress_in_items(): void
    {
        $result = ChunkedBatchDispatcher::make('t')
            ->items(range(1, 5))->chunk(2)
            ->job(static fn (array $chunk, int $index, int $total): ChunkRecordingJob => new ChunkRecordingJob($chunk, $index, $total))
            ->track('t:inline')
            ->runInline();

        self::assertSame([[1, 2], [3, 4], [5]], array_column(ChunkRecordingJob::$seen, 'chunk'));
        self::assertSame([1, 2, 3], array_column(ChunkRecordingJob::$seen, 'index'));
        self::assertSame([3, 3, 3], array_column(ChunkRecordingJob::$seen, 'total'));
        self::assertSame(['items' => 5, 'processed' => 5, 'chunks' => 3, 'failed' => 0, 'errors' => []], $result->toArray());

        $state = app(SeederRunTracker::class)->get('t:inline');
        self::assertNotNull($state);
        self::assertSame(SeederRunStatus::Completed, $state['status']);
        self::assertSame(5, $state['total']);
        self::assertSame(5, $state['processed']);
    }

    public function test_inline_records_a_failing_chunk_and_keeps_going(): void
    {
        $result = ChunkedBatchDispatcher::make('t')
            ->items(range(1, 6))->chunk(2)
            ->job(static fn (array $chunk, int $index, int $total): ChunkRecordingJob => new ChunkRecordingJob($chunk, $index, $total, failOn: 2))
            ->track('t:partial')
            ->runInline();

        self::assertSame(4, $result->processed);
        self::assertSame([['chunk' => 2, 'message' => 'chunk 2 failed']], $result->errors);
        self::assertFalse($result->succeeded());

        $state = app(SeederRunTracker::class)->get('t:partial');
        self::assertNotNull($state);
        self::assertSame(SeederRunStatus::Failed, $state['status']);
        self::assertSame(6, $state['processed']);
        self::assertSame(2, $state['failed']);
    }

    public function test_inline_stops_at_the_first_failure_when_failures_are_not_allowed(): void
    {
        $result = ChunkedBatchDispatcher::make('t')
            ->items(range(1, 6))->chunk(2)->allowFailures(false)
            ->job(static fn (array $chunk, int $index, int $total): ChunkRecordingJob => new ChunkRecordingJob($chunk, $index, $total, failOn: 1))
            ->runInline();

        self::assertSame(0, $result->processed);
        self::assertCount(1, ChunkRecordingJob::$seen);
    }

    public function test_queued_batch_runs_on_a_real_queue_and_settles_the_tracker(): void
    {
        config(['queue.default' => 'sync']);

        $batch = ChunkedBatchDispatcher::make('Seed things')
            ->items(range(1, 5))->chunk(2)
            ->job(static fn (array $chunk, int $index, int $total): ChunkRecordingJob => new ChunkRecordingJob($chunk, $index, $total, trackKey: 't:queued'))
            ->track('t:queued')
            ->dispatch();

        self::assertNotNull($batch);
        self::assertSame('Seed things', $batch->name);
        self::assertCount(3, ChunkRecordingJob::$seen);

        $state = app(SeederRunTracker::class)->get('t:queued');
        self::assertNotNull($state);
        self::assertSame(SeederRunStatus::Completed, $state['status']);
        self::assertSame(5, $state['processed']);
    }

    public function test_queued_batch_marks_the_run_failed_when_a_chunk_fails(): void
    {
        config(['queue.default' => 'sync']);

        // The sync driver rethrows a failed job, and stops dispatching the rest,
        // so the failing chunk is the last one: every job has then run once and
        // the batch's finally() callback fires, exactly as it would on a worker.
        try {
            ChunkedBatchDispatcher::make('t')
                ->items(range(1, 4))->chunk(2)
                ->job(static fn (array $chunk, int $index, int $total): ChunkRecordingJob => new ChunkRecordingJob($chunk, $index, $total, failOn: 2, trackKey: 't:queued-fail'))
                ->track('t:queued-fail')
                ->dispatch();
            self::fail('the sync driver should rethrow the failed chunk');
        } catch (RuntimeException $e) {
            self::assertSame('chunk 2 failed', $e->getMessage());
        }

        $state = app(SeederRunTracker::class)->get('t:queued-fail');
        self::assertNotNull($state);
        self::assertSame(SeederRunStatus::Failed, $state['status']);
        self::assertSame('1 of 2 chunks failed', $state['message']);
    }

    public function test_configure_reaches_the_pending_batch_before_dispatch(): void
    {
        config(['queue.default' => 'sync']);

        $batch = ChunkedBatchDispatcher::make('original')
            ->items([1])
            ->job(static fn (array $chunk, int $index, int $total): ChunkRecordingJob => new ChunkRecordingJob($chunk, $index, $total))
            ->configure(static fn (PendingBatch $pending): PendingBatch => $pending->name('renamed by configure'))
            ->dispatch();

        self::assertNotNull($batch);
        self::assertSame('renamed by configure', $batch->name);
    }

    public function test_no_items_dispatches_nothing(): void
    {
        self::assertNull(ChunkedBatchDispatcher::make('t')->job(static fn (): ChunkRecordingJob => new ChunkRecordingJob([], 1, 1))->dispatch());
    }

    public function test_running_without_a_job_is_a_clear_error(): void
    {
        $this->expectException(LogicException::class);

        ChunkedBatchDispatcher::make('t')->items([1])->runInline();
    }

    public function test_tracker_advance_counts_units_and_ignores_non_positive_steps(): void
    {
        $tracker = app(SeederRunTracker::class);
        $tracker->start('t:advance', 10);
        $tracker->advance('t:advance', by: 4);
        $tracker->advance('t:advance');
        $tracker->advance('t:advance', failed: true, by: 2);
        $tracker->advance('t:advance', by: 0);
        $tracker->advance('t:advance', by: -3);

        $state = $tracker->get('t:advance');
        self::assertNotNull($state);
        self::assertSame(7, $state['processed']);
        self::assertSame(2, $state['failed']);
    }

    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
    }
}

final class ChunkRecordingJob implements ShouldQueue
{
    use Batchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var list<array{chunk: list<mixed>, index: int, total: int}> */
    public static array $seen = [];

    /**
     * @param list<mixed> $chunk
     */
    public function __construct(
        public array $chunk,
        public int $index,
        public int $total,
        public ?int $failOn = null,
        public ?string $trackKey = null,
    ) {}

    public function handle(): void
    {
        self::$seen[] = ['chunk' => $this->chunk, 'index' => $this->index, 'total' => $this->total];

        if ($this->index === $this->failOn) {
            throw new RuntimeException("chunk {$this->index} failed");
        }

        if ($this->trackKey !== null) {
            app(SeederRunTracker::class)->advance($this->trackKey, by: count($this->chunk));
        }
    }
}

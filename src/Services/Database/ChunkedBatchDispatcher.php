<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Closure;
use Throwable;
use LogicException;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\OutputInterface;
use Simtabi\Laranail\Package\Tools\ValueObjects\ChunkRunResult;

/**
 * Split a large seeding workload (files, rows, ids) into chunks and run one job
 * per chunk — either as a queued `Bus::batch()` or inline in the current process.
 *
 *     ChunkedBatchDispatcher::make("icons:{$package}")
 *         ->items($files)->chunk(1000)
 *         ->job(fn (array $chunk, int $index, int $total) => new SeedIconsJob($chunk, $index, $total))
 *         ->queue('icons')
 *         ->track("icons:{$package}")
 *         ->dispatch();                       // or ->runInline()
 *
 * **Progress** goes through {@see SeederRunTracker} when {@see track()} names a
 * key: the run is started with the item count here, and settled (completed, or
 * failed when a chunk failed) when it ends. Per-chunk progress is the job's to
 * report — `app(SeederRunTracker::class)->advance($key, by: count($chunk))` —
 * because only the job knows when its chunk is actually done. Inline runs
 * advance the tracker themselves.
 *
 * **Serialization.** Batch callbacks are serialized onto the queue, so every
 * callback this class registers is a static closure capturing scalars only.
 * Anything added through {@see configure()} carries the same obligation: resolve
 * services from the container inside the closure rather than capturing them.
 */
final class ChunkedBatchDispatcher
{
    /** @var list<mixed> */
    private array $items = [];

    /** @var int<1, max> */
    private int $chunkSize = 1000;

    /** @var (Closure(list<mixed>, int, int): object)|null */
    private ?Closure $factory = null;

    private ?string $queue = null;

    private ?string $connection = null;

    private bool $allowFailures = true;

    private ?string $trackKey = null;

    /** @var list<Closure(PendingBatch): mixed> */
    private array $configurators = [];

    private function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Work a queue until it is empty, in this process — how a console command
     * finishes a batch it just dispatched without a separate worker.
     *
     * @param array<string, mixed> $options extra `queue:work` options
     */
    public static function drain(string $queue, ?OutputInterface $output = null, array $options = []): int
    {
        return Artisan::call('queue:work', [
            '--queue'           => $queue,
            '--stop-when-empty' => true,
            ...$options,
        ], $output);
    }

    /**
     * @param iterable<mixed> $items
     */
    public function items(iterable $items): self
    {
        $this->items = array_values(is_array($items) ? $items : iterator_to_array($items, false));

        return $this;
    }

    public function chunk(int $size): self
    {
        $this->chunkSize = max(1, $size);

        return $this;
    }

    /**
     * The job for one chunk: `fn (array $chunk, int $index, int $total): object`,
     * where `$index` is 1-based. Queued runs need a `ShouldQueue` job using
     * `Batchable`; inline runs only need a `handle()` method, which is called
     * through the container so its dependencies are injected.
     *
     * @param Closure(list<mixed>, int, int): object $factory
     */
    public function job(Closure $factory): self
    {
        $this->factory = $factory;

        return $this;
    }

    public function queue(?string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function connection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Keep running the remaining chunks after one fails (the default).
     */
    public function allowFailures(bool $allow = true): self
    {
        $this->allowFailures = $allow;

        return $this;
    }

    public function track(?string $key): self
    {
        $this->trackKey = $key;

        return $this;
    }

    /**
     * Adjust the pending batch before dispatch — add `then()`, `catch()`,
     * `progress()` callbacks and so on. Queued runs only.
     *
     * @param Closure(PendingBatch): mixed $configure
     */
    public function configure(Closure $configure): self
    {
        $this->configurators[] = $configure;

        return $this;
    }

    /**
     * @return list<list<mixed>>
     */
    public function chunks(): array
    {
        return array_chunk($this->items, $this->chunkSize);
    }

    /**
     * Queue one job per chunk as a single batch. Returns null, and dispatches
     * nothing, when there are no items.
     */
    public function dispatch(): ?Batch
    {
        $chunks = $this->chunks();

        if ($chunks === []) {
            return null;
        }

        $jobs = $this->makeJobs($chunks);

        $pending = Bus::batch($jobs)->name($this->name)->allowFailures($this->allowFailures);

        if ($this->queue !== null) {
            $pending->onQueue($this->queue);
        }

        if ($this->connection !== null) {
            $pending->onConnection($this->connection);
        }

        if ($this->trackKey !== null) {
            $key = $this->trackKey;

            $pending->finally(static function (Batch $batch) use ($key): void {
                $tracker = app(SeederRunTracker::class);

                $batch->failedJobs > 0
                    ? $tracker->fail($key, "{$batch->failedJobs} of {$batch->totalJobs} chunks failed")
                    : $tracker->complete($key);
            });

            app(SeederRunTracker::class)->start($key, count($this->items));
        }

        foreach ($this->configurators as $configure) {
            $configure($pending);
        }

        return $pending->dispatch();
    }

    /**
     * Run every chunk's job in this process, one after another. A chunk that
     * throws is recorded and, with {@see allowFailures()}, the rest still run.
     */
    public function runInline(): ChunkRunResult
    {
        $chunks = $this->chunks();
        $jobs = $this->makeJobs($chunks);
        $tracker = $this->trackKey !== null ? app(SeederRunTracker::class) : null;
        $processed = 0;
        $errors = [];

        $tracker?->start((string) $this->trackKey, count($this->items));

        foreach ($jobs as $i => $job) {
            $size = count($chunks[$i]);

            $handle = [$job, 'handle'];

            if (! is_callable($handle)) {
                throw new LogicException('ChunkedBatchDispatcher [' . $this->name . '] job ' . $job::class . ' has no handle() method.');
            }

            try {
                app()->call($handle);
                $processed += $size;
                $tracker?->advance((string) $this->trackKey, by: $size);
            } catch (Throwable $e) {
                $errors[] = ['chunk' => $i + 1, 'message' => $e->getMessage()];
                $tracker?->advance((string) $this->trackKey, failed: true, by: $size);

                if (! $this->allowFailures) {
                    break;
                }
            }

            gc_collect_cycles();
        }

        if ($tracker !== null) {
            $errors === []
                ? $tracker->complete((string) $this->trackKey)
                : $tracker->fail((string) $this->trackKey, count($errors) . ' of ' . count($chunks) . ' chunks failed');
        }

        return new ChunkRunResult(count($this->items), $processed, count($chunks), $errors);
    }

    /**
     * @param list<list<mixed>> $chunks
     *
     * @return list<object>
     */
    private function makeJobs(array $chunks): array
    {
        $factory = $this->factory;

        if (! $factory instanceof Closure) {
            throw new LogicException("ChunkedBatchDispatcher [{$this->name}] has no job(); call job() before running it.");
        }

        $total = count($chunks);
        $jobs = [];

        foreach ($chunks as $i => $chunk) {
            $jobs[] = $factory($chunk, $i + 1, $total);
        }

        return $jobs;
    }
}

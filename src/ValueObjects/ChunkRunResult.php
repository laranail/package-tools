<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\ValueObjects;

use JsonSerializable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The outcome of running a chunked workload inline
 * ({@see \Simtabi\Laranail\Package\Tools\Services\Database\ChunkedBatchDispatcher::runInline()}).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ChunkRunResult implements Arrayable, JsonSerializable
{
    /**
     * @param int $items Items in the workload
     * @param int $processed Items in chunks that completed
     * @param int $chunks Chunks attempted
     * @param list<array{chunk: int, message: string}> $errors One entry per failed chunk (1-based index)
     */
    public function __construct(
        public int $items,
        public int $processed,
        public int $chunks,
        public array $errors = [],
    ) {}

    public function failed(): int
    {
        return count($this->errors);
    }

    public function succeeded(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array{items: int, processed: int, chunks: int, failed: int, errors: list<array{chunk: int, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'items'     => $this->items,
            'processed' => $this->processed,
            'chunks'    => $this->chunks,
            'failed'    => $this->failed(),
            'errors'    => $this->errors,
        ];
    }

    /**
     * @return array{items: int, processed: int, chunks: int, failed: int, errors: list<array{chunk: int, message: string}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

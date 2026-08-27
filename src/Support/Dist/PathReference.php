<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Dist;

/**
 * One path a composer manifest references, and what became of it.
 */
final readonly class PathReference
{
    public function __construct(
        /** The manifest key that named it, e.g. `autoload.files`. */
        public string $key,
        public string $path,
        public ReferenceStatus $status,
    ) {}

    public function isFailure(): bool
    {
        return $this->status === ReferenceStatus::Stripped;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Dist;

/**
 * The outcome of auditing one revision.
 */
final readonly class DistIntegrityReport
{
    /**
     * @param list<PathReference> $references
     */
    public function __construct(
        public string $packageName,
        public string $revision,
        public array $references,
    ) {}

    /**
     * References whose file a dist install will not receive.
     *
     * @return list<PathReference>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->references,
            static fn (PathReference $r): bool => $r->isFailure(),
        ));
    }

    public function passed(): bool
    {
        return $this->failures() === [];
    }
}

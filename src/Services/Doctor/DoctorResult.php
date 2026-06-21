<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor;

/**
 * The outcome of a single DoctorCheck.
 *
 * Status semantics:
 *   - PASS: check succeeded; nothing to do.
 *   - WARN: check raised a non-fatal issue; informational.
 *   - FAIL: check found a real problem requiring user action.
 *   - SKIP: check did not run (e.g., precondition unmet).
 */
final readonly class DoctorResult
{
    public function __construct(
        public DoctorStatus $status,
        public string $message,
        /** @var array<string, scalar|array<scalar>> Optional structured detail. */
        public array $detail = [],
    ) {}

    /**
     * @param array<string, scalar|array<scalar>> $detail Optional structured detail.
     */
    public static function pass(string $message, array $detail = []): self
    {
        return new self(DoctorStatus::Pass, $message, $detail);
    }

    /**
     * @param array<string, scalar|array<scalar>> $detail Optional structured detail.
     */
    public static function warn(string $message, array $detail = []): self
    {
        return new self(DoctorStatus::Warn, $message, $detail);
    }

    /**
     * @param array<string, scalar|array<scalar>> $detail Optional structured detail.
     */
    public static function fail(string $message, array $detail = []): self
    {
        return new self(DoctorStatus::Fail, $message, $detail);
    }

    /**
     * @param array<string, scalar|array<scalar>> $detail Optional structured detail.
     */
    public static function skip(string $message, array $detail = []): self
    {
        return new self(DoctorStatus::Skip, $message, $detail);
    }
}

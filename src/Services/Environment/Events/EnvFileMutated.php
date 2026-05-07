<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Environment\Events;

/**
 * Fired whenever EnvFileService writes to the host's .env.
 *
 * Subscribers can use this for audit logging, cache busting, or
 * change-tracking dashboards. Atomic write ordering is:
 *
 *   1. backup → .env.bak.<timestamp>
 *   2. write tmp → .env.tmp.<pid>
 *   3. atomic rename .env.tmp.<pid> → .env
 *   4. dispatch EnvFileMutated
 *
 * The event therefore arrives only after a successful, durable write.
 */
final readonly class EnvFileMutated
{
    public function __construct(
        public string $path,
        /** @var list<string> Keys added by this write. */
        public array $addedKeys,
        public string $backupPath,
        /** @var "appendIfMissing" | "appendBlock" | "forceSet" */
        public string $action,
    ) {}
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederAutorun;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederBundle;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederExecutor;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;
use Simtabi\Laranail\Package\Tools\Support\RuntimeConfigurator;
use Throwable;

/**
 * Executes ONE seeder bundle on a queue worker. The payload carries only
 * the bundle KEY (definitions can hold closures, which don't serialize) —
 * the worker's own provider boot re-registers every bundle, and handle()
 * re-resolves it from the shared registry. A key that no longer resolves
 * warns and no-ops instead of crashing the worker.
 */
final class RunSeederBundleJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly string $bundleKey,
        public readonly SeederExecutionMode $mode = SeederExecutionMode::Queued,
    ) {
        $this->tries = (int) config('package-tools.seeders.queue.tries', 1);
        $this->timeout = (int) config('package-tools.seeders.queue.timeout', 300);

        $queue = config('package-tools.seeders.queue.name');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }

        $connection = config('package-tools.seeders.queue.connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }
    }

    public function handle(SeederRegistry $registry, SeederExecutor $executor, SeederAutorun $autorun): void
    {
        $bundle = $registry->get($this->bundleKey);

        if (! $bundle instanceof SeederBundle) {
            Log::warning("Package seeder bundle '{$this->bundleKey}' is not registered in this process; skipping.", [
                'job' => self::class,
            ]);

            return;
        }

        try {
            RuntimeConfigurator::forQueueJob()->timeout($this->timeout)->apply();
        } catch (Throwable) {
            // Runtime hardening is best-effort.
        }

        $scoped = new SeederRegistry;
        $scoped->registerBundle($bundle);

        $autorun->markExecuted($this->bundleKey);
        $executor->run($scoped, $this->mode);
    }
}

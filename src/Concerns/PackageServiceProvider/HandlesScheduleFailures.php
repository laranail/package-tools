<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Exceptions\ScheduleConfigurationException;
use Simtabi\Laranail\Package\Tools\Facades\PackageActions;

/**
 * Shared policy for how a package's schedule-configuration failure is
 * handled while the `Schedule` resolves. Every failure is logged to the
 * package's own logfile with structured context; then:
 *
 *  - STRICT (default outside production): the exception is rethrown so the
 *    author sees the misconfiguration immediately — a scheduling typo is a
 *    code bug, not runtime data.
 *  - LENIENT (production, or `package-tools.scheduling.strict => false`):
 *    the entry is skipped so one package's typo can't take down the whole
 *    scheduler (every other package's tasks still register).
 *
 * Override the auto behavior with `package-tools.scheduling.strict`
 * (bool). Left null it is strict everywhere except production.
 */
trait HandlesScheduleFailures
{
    protected function handleScheduleFailure(ScheduleConfigurationException $e): void
    {
        $strict = $this->schedulingIsStrict();

        // Route through the reporter (which owns the log line, writing to the
        // package's own logfile) and dispatch a Schedule/PackageActionFailed:
        // Failed when strict (about to rethrow), Cancelled when the entry is
        // skipped.
        PackageActions::forPackage($this->package->log())->fromThrowable(
            PackageActionType::Schedule,
            $this->scheduleActionName($e),
            $this->package->name,
            $e,
            $strict ? FailureReason::Failed : FailureReason::Cancelled,
            $e->context,
        );

        if ($strict) {
            throw $e;
        }
    }

    private function scheduleActionName(ScheduleConfigurationException $e): string
    {
        foreach (['command', 'bundle'] as $key) {
            $value = $e->context[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return 'schedule';
    }

    protected function schedulingIsStrict(): bool
    {
        $configured = config('package-tools.scheduling.strict');

        if (is_bool($configured)) {
            return $configured;
        }

        // Auto: strict everywhere except production, so authors catch
        // scheduling typos in local/CI while production stays resilient.
        return ! function_exists('app') || ! app()->isProduction();
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Exceptions\ScheduleConfigurationException;
use Simtabi\Laranail\Package\Tools\Facades\PackageActions;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * How a package's schedule-configuration failure is handled while the
 * `Schedule` resolves. It follows the package-wide {@see FailurePolicy}
 * (strict in dev, lenient in prod): every failure is logged to the package's
 * own logfile; then it is rethrown (strict) so the author sees the typo, or
 * the entry is skipped (lenient) so one package's typo can't take down the
 * whole scheduler.
 *
 * `package-tools.scheduling.strict` (bool) overrides strictness for
 * scheduling specifically; left null it defers to `resilience.strict`.
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

        // Defer to the package-wide resilience policy (strict except prod).
        return FailurePolicy::isStrict();
    }
}

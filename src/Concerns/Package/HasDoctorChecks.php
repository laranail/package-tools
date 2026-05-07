<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorCheck;

/**
 * Adds `Package::hasDoctorCheck()` — fluent step to register a health
 * check. The DoctorService collects all registered checks; the
 * `php artisan package:doctor` command runs them.
 */
trait HasDoctorChecks
{
    /** @var list<class-string<DoctorCheck>|DoctorCheck> */
    protected array $doctorChecks = [];

    /**
     * Register a doctor check by class-string or instance.
     *
     * @param class-string<DoctorCheck>|DoctorCheck $check
     */
    public function hasDoctorCheck(string|DoctorCheck $check): static
    {
        $this->doctorChecks[] = $check;

        return $this;
    }

    /** @return list<class-string<DoctorCheck>|DoctorCheck> */
    public function getDoctorChecks(): array
    {
        return $this->doctorChecks;
    }
}

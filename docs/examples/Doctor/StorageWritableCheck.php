<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a second doctor check covering warn / fail / skip outcomes.
|------------------------------------------------------------------------------
| Registered via a second ->hasDoctorCheck() call. Shows the three non-pass
| DoctorResult factories:
|   - skip() when a precondition is not met (nothing to check yet)
|   - fail() when a real problem needs user action
|   - warn() for a non-fatal heads-up
| run() must never throw; it always returns a DoctorResult.
*/

namespace Acme\Hello\Doctor;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

final class StorageWritableCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'hello:storage-writable';
    }

    public function description(): string
    {
        return 'Verify the package storage directory exists and is writable';
    }

    public function run(): DoctorResult
    {
        $dir = storage_path('app/hello');

        if (! File::exists($dir)) {
            // Precondition unmet: the directory is created on first use, so
            // there is nothing to assert yet.
            return DoctorResult::skip('Storage directory not created yet; nothing to check.', ['path' => $dir]);
        }

        if (! is_writable($dir)) {
            return DoctorResult::fail('Storage directory is not writable.', ['path' => $dir]);
        }

        $free = @disk_free_space($dir);
        if (is_float($free) && $free < 10 * 1024 * 1024) {
            return DoctorResult::warn('Less than 10 MB free on the storage volume.', ['free_bytes' => (int) $free]);
        }

        return DoctorResult::pass('Storage directory is present and writable.', ['path' => $dir]);
    }
}

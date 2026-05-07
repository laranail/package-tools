<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a DoctorCheck for `php artisan package:doctor`.
|------------------------------------------------------------------------------
| Wired up via the example HelloPackageServiceProvider's hasDoctorCheck()
| call. When `php artisan package:doctor` runs, it instantiates this
| class, calls run(), and renders the result in the TTY (or JSON with
| --json).
*/

namespace Acme\Hello\Doctor;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\PackageTools\Services\Doctor\DoctorResult;

final class HelloHealthCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'hello:config-published';
    }

    public function description(): string
    {
        return 'Verify that config/hello.php has been published';
    }

    public function run(): DoctorResult
    {
        $configPath = config_path('hello.php');

        if (! File::exists($configPath)) {
            return DoctorResult::warn(
                'Config file not yet published — run `php artisan vendor:publish --tag=hello-config`',
                ['expected' => $configPath],
            );
        }

        $required = ['greeting', 'recipient'];
        $config = require $configPath;
        $missing = array_diff($required, array_keys($config));

        if ($missing !== []) {
            return DoctorResult::fail(
                'Config is published but missing required keys',
                ['missing' => $missing, 'path' => $configPath],
            );
        }

        return DoctorResult::pass('Config published with all required keys', ['path' => $configPath]);
    }
}

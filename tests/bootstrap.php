<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Bootstrap
|--------------------------------------------------------------------------
|
| This file is used to bootstrap the test environment for the package.
| It ensures all necessary dependencies are loaded and the environment
| is properly configured for testing.
|
*/

// Load the Composer autoloader (vendor lives at repo root, two levels up from tests/Package/)
require __DIR__ . '/../vendor/autoload.php';

// Set the default timezone
date_default_timezone_set('UTC');

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define testing constants
if (! defined('TESTING')) {
    define('TESTING', true);
}

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

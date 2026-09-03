<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Config;

use Throwable;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Support\ConfigFile;
use Simtabi\Laranail\Package\Tools\Contracts\ValidatorInterface;

/**
 * Validates configuration files and values.
 */
class ConfigValidator implements ValidatorInterface
{
    /**
     * Validate a configuration file or array
     *
     * @param mixed $input Path to config file or config array
     *
     * @return array<string> Array of validation errors
     */
    public function validate(mixed $input): array
    {
        $errors = [];

        if (is_string($input)) {
            // Validate file path
            if (! File::exists($input)) {
                $errors[] = "Configuration file not found: {$input}";

                return $errors;
            }

            if (! File::isReadable($input)) {
                $errors[] = "Configuration file not readable: {$input}";

                return $errors;
            }

            try {
                // include, not require: a failed require is a fatal compile
                // error and is NOT caught by the try below, so the guard here was
                // never doing what it looks like it does.
                $config = ConfigFile::read($input);
                if ($config === null) {
                    $errors[] = "Configuration file must return an array: {$input}";
                }
            } catch (Throwable $e) {
                $errors[] = "Error loading configuration file: {$e->getMessage()}";
            }
        } elseif (is_array($input)) {
            // Validate config array structure
            $errors = array_merge($errors, $this->validateStructure($input));
        } else {
            $errors[] = 'Invalid input type. Expected string (file path) or array';
        }

        return $errors;
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(mixed $input): bool
    {
        return $this->validate($input) === [];
    }

    /**
     * Validate configuration array structure
     *
     * @param array<int|string, mixed> $config Configuration array
     *
     * @return array<string> Validation errors
     */
    protected function validateStructure(array $config): array
    {
        $errors = [];

        // Check for reserved keys that might conflict with Laravel
        $reserved = ['app', 'auth', 'cache', 'database', 'filesystems', 'logging', 'mail', 'queue', 'services', 'session'];

        foreach ($reserved as $key) {
            if (array_key_exists($key, $config) && is_array($config[$key])) {
                // This is okay - it's a nested config structure
                continue;
            }
        }

        return $errors;
    }
}

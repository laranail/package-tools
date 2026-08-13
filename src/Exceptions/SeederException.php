<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown when a package seeding operation fails.
 */
class SeederException extends Exception
{
    /** @var array<string, mixed> */
    public array $context = [];

    public static function classNotFound(string $seederClass): self
    {
        $e = new self("Seeder class does not exist: {$seederClass}", 4001);
        $e->context = ['seeder' => $seederClass];

        return $e;
    }

    public static function invalidClass(string $seederClass): self
    {
        $e = new self("Class is not a valid seeder: {$seederClass}", 4002);
        $e->context = ['seeder' => $seederClass];

        return $e;
    }

    public static function executionFailed(string $seederClass, Throwable $previous): self
    {
        $e = new self("Seeder execution failed: {$seederClass}", 4003, $previous);
        $e->context = ['seeder' => $seederClass, 'error' => $previous->getMessage()];

        return $e;
    }

    public static function discoveryFailed(string $path, string $reason): self
    {
        $e = new self("Seeder discovery failed at {$path}: {$reason}", 4004);
        $e->context = ['path' => $path, 'reason' => $reason];

        return $e;
    }

    /**
     * Faker is a dev dependency and may legitimately be absent.
     *
     * This throws rather than installing it. The code this replaces shelled out
     * to `composer install` and then called `exit(1)` — from inside a library
     * method, taking the whole process with it, in whatever context it happened
     * to be called from.
     */
    public static function missingFaker(): self
    {
        $e = new self(
            'fakerphp/faker is not installed, so no generator can be created. '
            . 'Run `composer require --dev fakerphp/faker`.',
            4005,
        );
        $e->context = ['package' => 'fakerphp/faker'];

        return $e;
    }

    public static function seedFileMissing(string $path): self
    {
        $e = new self("Seed file not found: {$path}", 4006);
        $e->context = ['path' => $path];

        return $e;
    }
}

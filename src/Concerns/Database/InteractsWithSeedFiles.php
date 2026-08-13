<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Database;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Exceptions\SeederException;

/**
 * The two things every package seeder ends up rewriting: a Faker generator and
 * a place to keep seed fixtures.
 *
 * ## `fake()` throws; it does not install anything
 *
 * The implementation this replaces ran `composer install` from inside the
 * method and then called `exit(1)` when that did not help — from a library, in
 * whatever context it happened to be called from, taking the process with it. A
 * seeder run inside a queue worker or a test suite would simply vanish.
 *
 * So a missing generator is a `SeederException`, which a caller can catch,
 * report, and decide about. `fakerphp/faker` stays a dev dependency of this
 * package and is only ever `suggest`-ed to consumers.
 *
 * ## The generator is memoized per instance
 *
 * Not for speed — for reproducibility. `Factory::create()` seeds a fresh Mersenne
 * Twister each call, so a seeder that built a new generator per record could not
 * be made deterministic by seeding once at the start of a run.
 */
trait InteractsWithSeedFiles
{
    private ?Generator $fakerGenerator = null;

    private ?string $seedFileBasePath = null;

    /**
     * @throws SeederException when `fakerphp/faker` is not installed
     */
    protected function fake(?string $locale = null): Generator
    {
        if ($locale === null && $this->fakerGenerator instanceof Generator) {
            return $this->fakerGenerator;
        }

        if (! class_exists(Factory::class)) {
            throw SeederException::missingFaker();
        }

        $generator = Factory::create($locale ?? $this->fakerLocale());

        // A locale-specific generator is a one-off and does not become the
        // memoized default, or the first localized call would silently
        // repoint every subsequent one.
        if ($locale !== null) {
            return $generator;
        }

        return $this->fakerGenerator = $generator;
    }

    /**
     * Reseed the generator so a run can be reproduced exactly.
     */
    protected function seedFaker(int $seed): Generator
    {
        $generator = $this->fake();
        $generator->seed($seed);

        return $generator;
    }

    protected function fakerLocale(): string
    {
        $locale = config('package-tools.seeders.faker_locale', Factory::DEFAULT_LOCALE);

        return is_string($locale) && $locale !== '' ? $locale : Factory::DEFAULT_LOCALE;
    }

    /**
     * Where this seeder's fixture files live.
     */
    protected function seedFileBasePath(): string
    {
        if (is_string($this->seedFileBasePath) && $this->seedFileBasePath !== '') {
            return $this->seedFileBasePath;
        }

        $configured = config('package-tools.seeders.files_path');

        return is_string($configured) && $configured !== ''
            ? $configured
            : database_path('seeders/files');
    }

    protected function setSeedFileBasePath(string $path): static
    {
        $this->seedFileBasePath = rtrim($path, '/\\');

        return $this;
    }

    /**
     * Resolve a fixture path. Absolute paths pass through unchanged.
     */
    protected function seedFilePath(string $relative): string
    {
        if (str_starts_with($relative, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $relative) === 1) {
            return $relative;
        }

        return $this->seedFileBasePath() . '/' . ltrim($relative, '/\\');
    }

    protected function seedFileExists(string $relative): bool
    {
        return File::isFile($this->seedFilePath($relative));
    }

    /**
     * @throws SeederException when the file is not there
     */
    protected function seedFileContents(string $relative): string
    {
        $path = $this->seedFilePath($relative);

        if (! File::isFile($path)) {
            throw SeederException::seedFileMissing($path);
        }

        return File::get($path);
    }

    /**
     * A seed fixture decoded as JSON.
     *
     * @return array<array-key, mixed>
     *
     * @throws SeederException when the file is missing or is not a JSON array
     */
    protected function seedFileJson(string $relative): array
    {
        $decoded = json_decode($this->seedFileContents($relative), true);

        if (! is_array($decoded)) {
            throw SeederException::discoveryFailed(
                $this->seedFilePath($relative),
                'the file does not decode to a JSON array',
            );
        }

        return $decoded;
    }

    /**
     * Every fixture in a subdirectory, sorted, as absolute paths.
     *
     * @return list<string>
     */
    protected function seedFiles(string $relative = '', ?string $extension = null): array
    {
        $directory = $relative === '' ? $this->seedFileBasePath() : $this->seedFilePath($relative);

        if (! File::isDirectory($directory)) {
            return [];
        }

        $paths = [];

        foreach (File::files($directory) as $file) {
            if ($extension !== null && $file->getExtension() !== ltrim($extension, '.')) {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        sort($paths);

        return $paths;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Dist;

use JsonException;

/**
 * Every path composer.json references must survive `git archive`.
 *
 * ## The bug this exists for
 *
 * `laranail/enumerator` declared `extra.phpstan.includes: ["extension.neon"]`
 * and, in the same repository, `.gitattributes` carried
 * `/extension.neon export-ignore`. Both lines are individually reasonable --
 * one tells `phpstan/extension-installer` to load the file, the other reads
 * like ordinary dev-file housekeeping. Together they ship a package whose
 * generated PHPStan config points at a file the archive does not contain, and
 * every consumer with `phpstan/extension-installer` gets:
 *
 *     Config file .../vendor/laranail/enumerator/extension.neon
 *     does not exist or isn't readable
 *
 * naming a path inside `vendor/`, with nothing pointing back at the package
 * that caused it. It survived for months because it needs two conditions --
 * the installer *and* a dist install -- and no package in the org had both
 * until one did.
 *
 * `autoload.files` is the worse version of the same shape: Composer `require`s
 * those on every autoload, so a stripped one is a fatal rather than a degraded
 * check.
 */
final readonly class DistIntegrityAuditor
{
    public function __construct(private RevisionReader $reader) {}

    /**
     * @throws CouldNotReadRevision|JsonException
     */
    public function audit(string $revision = 'HEAD'): DistIntegrityReport
    {
        /** @var array<string, mixed> $composer */
        $composer = json_decode($this->reader->manifest($revision), true, 512, JSON_THROW_ON_ERROR);

        $archived = $this->reader->archivedPaths($revision);
        $tracked = $this->reader->trackedPaths($revision);

        $references = [];

        foreach (self::referencedPaths($composer) as [$key, $path]) {
            $references[] = new PathReference($key, $path, match (true) {
                ! $this->contains($tracked, $path) => ReferenceStatus::NotCommitted,
                $this->contains($archived, $path) => ReferenceStatus::Shipped,
                default => ReferenceStatus::Stripped,
            });
        }

        return new DistIntegrityReport(
            packageName: is_string($composer['name'] ?? null) ? $composer['name'] : '?',
            revision: $revision,
            references: $references,
        );
    }

    /**
     * The paths the manifest promises a consumer will find.
     *
     * Deliberately not every key -- only those where a missing file is a
     * failure in the *consumer's* install rather than a nuisance in this
     * repository.
     *
     * @param array<string, mixed> $composer
     * @return list<array{0: string, 1: string}>
     */
    public static function referencedPaths(array $composer): array
    {
        $paths = [];

        foreach ((array) ($composer['extra']['phpstan']['includes'] ?? []) as $include) {
            $paths[] = ['extra.phpstan.includes', (string) $include];
        }

        foreach ((array) ($composer['bin'] ?? []) as $binary) {
            $paths[] = ['bin', (string) $binary];
        }

        foreach (['psr-4', 'psr-0'] as $standard) {
            foreach ((array) ($composer['autoload'][$standard] ?? []) as $directories) {
                foreach ((array) $directories as $directory) {
                    $paths[] = ["autoload.{$standard}", rtrim((string) $directory, '/')];
                }
            }
        }

        foreach ((array) ($composer['autoload']['files'] ?? []) as $file) {
            $paths[] = ['autoload.files', (string) $file];
        }

        return $paths;
    }

    /**
     * @param list<string> $haystack
     */
    private function contains(array $haystack, string $path): bool
    {
        foreach ($haystack as $candidate) {
            if ($candidate === $path || str_starts_with($candidate, $path . '/')) {
                return true;
            }
        }

        return false;
    }
}

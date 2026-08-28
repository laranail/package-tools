<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Simtabi\Laranail\Package\Tools\Support\Dist\RevisionReader;
use Simtabi\Laranail\Package\Tools\Support\Dist\ReferenceStatus;
use Simtabi\Laranail\Package\Tools\Support\Dist\DistIntegrityReport;
use Simtabi\Laranail\Package\Tools\Support\Dist\CouldNotReadRevision;
use Simtabi\Laranail\Package\Tools\Support\Dist\DistIntegrityAuditor;

class DistIntegrityAuditorTest extends TestCase
{
    public function test_it_fails_a_referenced_path_the_archive_strips(): void
    {
        // The exact shape this audit exists for: enumerator declared
        // extra.phpstan.includes: ["extension.neon"] while .gitattributes
        // carried /extension.neon export-ignore.
        $report = $this->audit(
            manifest: [
                'name'  => 'laranail/enumerator',
                'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
            ],
            tracked: ['extension.neon', 'src/Foo.php'],
            archived: ['src/Foo.php'],
        );

        $this->assertFalse($report->passed());
        $this->assertCount(1, $report->failures());
        $this->assertSame('extra.phpstan.includes', $report->failures()[0]->key);
        $this->assertSame('extension.neon', $report->failures()[0]->path);
        $this->assertSame(ReferenceStatus::Stripped, $report->failures()[0]->status);
    }

    public function test_it_passes_when_every_referenced_path_is_in_the_archive(): void
    {
        $report = $this->audit(
            manifest: ['name' => 'laranail/atlas', 'autoload' => ['psr-4' => ['A\\B\\' => 'src']]],
            tracked: ['src/Foo.php'],
            archived: ['src/Foo.php'],
        );

        $this->assertTrue($report->passed());
        $this->assertSame(ReferenceStatus::Shipped, $report->references[0]->status);
    }

    public function test_a_declared_but_uncommitted_path_is_reported_and_not_failed(): void
    {
        // Composer tolerates a missing psr-4 directory, so this must not fail
        // the build -- but it is still a manifest describing something absent.
        $report = $this->audit(
            manifest: ['name' => 'laranail/atlas', 'autoload' => ['psr-4' => ['A\\B\\' => 'src']]],
            tracked: [],
            archived: [],
        );

        $this->assertTrue($report->passed());
        $this->assertSame(ReferenceStatus::NotCommitted, $report->references[0]->status);
    }

    public function test_a_stripped_binary_is_a_failure(): void
    {
        // autoload.files and bin are the worse version of the same shape --
        // Composer requires those on every autoload, so a stripped one is a
        // fatal rather than a degraded check.
        $report = $this->audit(
            manifest: ['name' => 'laranail/pkg', 'bin' => ['bin/thing'], 'autoload' => ['files' => ['helpers.php']]],
            tracked: ['bin/thing', 'helpers.php'],
            archived: [],
        );

        $this->assertCount(2, $report->failures());
        $this->assertSame(['bin', 'autoload.files'], array_column($report->failures(), 'key'));
    }

    public function test_a_directory_reference_is_satisfied_by_a_file_beneath_it(): void
    {
        $report = $this->audit(
            manifest: ['name' => 'laranail/pkg', 'autoload' => ['psr-4' => ['A\\' => 'src/']]],
            tracked: ['src/Deep/Nested.php'],
            archived: ['src/Deep/Nested.php'],
        );

        $this->assertTrue($report->passed());
    }

    public function test_referenced_paths_covers_every_consumer_facing_key(): void
    {
        $paths = DistIntegrityAuditor::referencedPaths([
            'extra'    => ['phpstan' => ['includes' => ['ext.neon']]],
            'bin'      => ['bin/x'],
            'autoload' => [
                'psr-4' => ['A\\' => 'src/'],
                'psr-0' => ['B' => 'legacy/'],
                'files' => ['helpers.php'],
            ],
        ]);

        $this->assertSame([
            ['extra.phpstan.includes', 'ext.neon'],
            ['bin', 'bin/x'],
            ['autoload.psr-4', 'src'],
            ['autoload.psr-0', 'legacy'],
            ['autoload.files', 'helpers.php'],
        ], $paths);
    }

    public function test_an_unreadable_revision_is_an_exception_not_a_silent_pass(): void
    {
        $this->expectException(CouldNotReadRevision::class);

        (new DistIntegrityAuditor(new class implements RevisionReader
        {
            public function manifest(string $revision): string
            {
                throw CouldNotReadRevision::manifest($revision);
            }

            public function archivedPaths(string $revision): array
            {
                return [];
            }

            public function trackedPaths(string $revision): array
            {
                return [];
            }
        }))->audit();
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<string> $tracked
     * @param list<string> $archived
     */
    private function audit(array $manifest, array $tracked, array $archived): DistIntegrityReport
    {
        $reader = new readonly class($manifest, $tracked, $archived) implements RevisionReader
        {
            /**
             * @param array<string, mixed> $manifest
             * @param list<string> $tracked
             * @param list<string> $archived
             */
            public function __construct(
                private array $manifest,
                private array $tracked,
                private array $archived,
            ) {}

            public function manifest(string $revision): string
            {
                return json_encode($this->manifest, JSON_THROW_ON_ERROR);
            }

            public function archivedPaths(string $revision): array
            {
                return $this->archived;
            }

            public function trackedPaths(string $revision): array
            {
                return $this->tracked;
            }
        };

        return (new DistIntegrityAuditor($reader))->audit();
    }
}

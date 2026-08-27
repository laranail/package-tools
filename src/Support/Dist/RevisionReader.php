<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Dist;

/**
 * Reads one revision of a repository.
 *
 * The audit deliberately takes manifest and archive from the *same* revision.
 * An earlier version compared the working-tree manifest against the HEAD
 * archive and reported a package mid-refactor as broken -- its manifest already
 * named the new path, its archive still held the old one. In a checkout several
 * people work in, an audit that flags in-flight work is worse than no audit,
 * because people learn to ignore it.
 */
interface RevisionReader
{
    /** The raw composer.json at this revision. */
    public function manifest(string $revision): string;

    /**
     * Paths present in `git archive` output -- what a dist install receives.
     *
     * @return list<string>
     */
    public function archivedPaths(string $revision): array;

    /**
     * Paths tracked in the tree, whether or not the archive keeps them.
     *
     * @return list<string>
     */
    public function trackedPaths(string $revision): array;
}

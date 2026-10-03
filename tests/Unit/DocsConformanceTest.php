<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Testing\AssertsDocsConformance;

/**
 * The docs standard, asserted rather than described.
 *
 * The assertions themselves moved to `Testing\AssertsDocsConformance` on 2026-09-28 so
 * that every package in the estate runs the same ones. They were previously copy-pasted
 * four different ways — here, in `laranail/emojis`, in `laranail/tenancy-boilerplate`,
 * and in a fifth shared by the five `ichava/icon-sets-*` packs — which is four things to
 * keep in step, and they were not in step.
 *
 * Nothing was dropped in the move: every assertion this file used to make, it still
 * makes, through the trait. The trait adds two the standard gained since — a required
 * `## Quick start guide and usage`, and a LICENSE that agrees with `composer.json`.
 *
 * This file stays as the package's own entry point. Deleting it would mean the gate runs
 * only where someone remembered to wire it.
 */
final class DocsConformanceTest extends TestCase
{
    use AssertsDocsConformance;

    /**
     * 20 is this package's floor, not the estate's: it ships 43 pages, and a glob that
     * suddenly matches fewer has broken rather than found a clean tree.
     */
    private const int MINIMUM_PAGES = 20;

    #[Test]
    public function the_docs_tree_conforms_to_the_org_standard(): void
    {
        $this->assertDocsConformToStandard($this->packageRoot(), self::MINIMUM_PAGES);
    }

    private function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}

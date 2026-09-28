<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The docs standard, asserted rather than described — shared across the estate.
 *
 * The rules are the mechanical ones from the org standard in `~/.claude/CLAUDE.md`:
 * an H1 that names the page, a one-line summary under it, a `---` rule and a
 * relative footer at the end, a `## Quick start` in the README, no decorative
 * emoji, and no `docs/README.md` or `docs/adr/` (the index is the README's
 * Documentation section; rationale is prose in architecture.md).
 *
 * These drift silently. Fourteen pages in this package had lost the `---` rule and
 * two had lost their summary, and nothing failed — a docs tree is only ever read one
 * page at a time, so an inconsistency between pages is invisible from inside any of
 * them.
 *
 * It lives here rather than in each package because it was previously copy-pasted
 * four different ways: this package, `laranail/emojis`, `laranail/tenancy-boilerplate`
 * and a fifth shared by the five `ichava/icon-sets-*` packs. Four implementations of
 * one standard is four things to keep in step, and they were not in step.
 *
 * Mix into any PHPUnit or Pest test case:
 *
 *     final class DocsConformanceTest extends TestCase
 *     {
 *         use AssertsDocsConformance;
 *
 *         public function test_docs_conform(): void
 *         {
 *             $this->assertDocsConformToStandard(dirname(__DIR__, 2));
 *         }
 *     }
 *
 * Every assertion takes the package root explicitly. Nothing here resolves it by
 * walking up from `__DIR__`: this file ships inside `vendor/`, so a relative walk
 * would find the consumer's vendor directory rather than the package under test.
 */
trait AssertsDocsConformance
{
    private const string DOCS_FOOTER = '[← Docs index](';

    private const string DOCS_INDEX_HEADING = '## <a name="documentation"></a>Documentation';

    /**
     * Run every docs assertion against a package root.
     *
     * @param  int  $minimumPages  the floor below which the glob is assumed broken rather
     *                             than the tree assumed clean. Per package: this one ships
     *                             43 pages, `@ichava/motion` ships 6. Never 0.
     */
    protected function assertDocsConformToStandard(string $root, int $minimumPages = 1): void
    {
        $this->assertDocsPagesExist($root, $minimumPages);
        $this->assertEveryDocsPageOpensWithATitleAndSummary($root);
        $this->assertEveryDocsPageEndsWithARuleAndFooter($root);
        $this->assertNoDocsPageCarriesADecorativeEmoji($root);
        $this->assertDocsTreeHasNoIndexPageOrAdrDirectory($root);
        $this->assertReadmeIndexListsEveryPage($root);
        $this->assertReadmeHasAQuickStart($root);
        $this->assertLicenceFilePresentAndAgreesWithManifest($root);
    }

    /**
     * A glob that stops matching reports a clean tree rather than a broken search, so
     * every other assertion in this trait would pass vacuously. This is the one that
     * has to fail first.
     */
    protected function assertDocsPagesExist(string $root, int $minimumPages = 1): void
    {
        $this->assertGreaterThanOrEqual(
            max(1, $minimumPages),
            count($this->docsPages($root)),
            "fewer than {$minimumPages} docs pages were inspected in {$root}/docs — "
            .'treat this as a broken glob, not a clean tree',
        );
    }

    protected function assertEveryDocsPageOpensWithATitleAndSummary(string $root): void
    {
        foreach ($this->docsPages($root) as $page) {
            $lines = $this->docsPageLines($root, $page);

            $this->assertStringStartsWith('# ', $lines[0], "{$page} does not open with an H1");

            // The summary is prose: not a heading, fence, list, table or quote.
            $this->assertNotEmpty($lines[1] ?? '', "{$page} has no summary under its H1");
            $this->assertDoesNotMatchRegularExpression(
                '/^(#|```|\||- |\* |> )/',
                $lines[1],
                "{$page}: the line under the H1 is [{$lines[1]}], not a one-line summary",
            );
        }
    }

    /**
     * Depth comes from the path, never from the eye — a hardcoded `../../` passes on the
     * pages it happens to match and silently skips the rest.
     *
     * The footer is asserted to appear exactly once, which is what fails a page carrying
     * the index link both above its title and in the footer. A breadcrumb-above-H1 rule
     * was added to the standard on 2026-09-21 and withdrawn the next day; this assertion
     * is what stops it being reintroduced by hand.
     */
    protected function assertEveryDocsPageEndsWithARuleAndFooter(string $root): void
    {
        foreach ($this->docsPages($root) as $page) {
            $lines = $this->docsPageLines($root, $page);
            $body = (string) file_get_contents($root.'/'.$page);

            // docs/x.md -> ../ ; docs/tools/x.md and docs/recipes/x.md -> ../../
            $depth = substr_count($page, '/');
            $expected = self::DOCS_FOOTER.str_repeat('../', $depth).'README.md#documentation)';

            $this->assertSame($expected, end($lines), "{$page} has the wrong docs-index footer");
            $this->assertSame('---', prev($lines), "{$page} is missing the `---` rule before its footer");
            $this->assertSame(
                1,
                substr_count($body, self::DOCS_FOOTER),
                "{$page} should carry the index link exactly once, as its footer",
            );
        }
    }

    protected function assertNoDocsPageCarriesADecorativeEmoji(string $root): void
    {
        // Semantic glyphs are fine and the footer arrow is one; status emoji are not.
        foreach ($this->docsPages($root) as $page) {
            $this->assertSame(
                0,
                preg_match(
                    '/[\x{1F300}-\x{1FAFF}\x{2705}\x{274C}\x{26A0}\x{2757}\x{2B50}]/u',
                    (string) file_get_contents($root.'/'.$page),
                ),
                "{$page} contains a decorative emoji",
            );
        }
    }

    protected function assertDocsTreeHasNoIndexPageOrAdrDirectory(string $root): void
    {
        // The index is the README's Documentation section; two indexes drift.
        // Architectural rationale is prose in architecture.md, not an adr/ tree.
        $this->assertFileDoesNotExist($root.'/docs/README.md');
        $this->assertDirectoryDoesNotExist($root.'/docs/adr');
    }

    /** Asserted in both directions: every page is linked, and every link is a real page. */
    protected function assertReadmeIndexListsEveryPage(string $root): void
    {
        $readme = (string) file_get_contents($root.'/README.md');

        $section = explode(self::DOCS_INDEX_HEADING, $readme, 2)[1] ?? '';
        $section = explode("\n## ", $section, 2)[0];

        $this->assertNotSame('', trim($section), 'the README has no Documentation section');

        preg_match_all('#\]\((docs/[^)\#]+\.md)\)#', $section, $m);

        $linked = array_unique($m[1]);
        sort($linked);

        $this->assertSame(
            $this->docsPages($root),
            array_values($linked),
            'the README Documentation index and docs/ have drifted apart',
        );
    }

    /**
     * `## Quick start` became required on 2026-09-28. It is one runnable example and two
     * links, sitting between `Install` and `Documentation` — a reader should never have to
     * leave the README to see what the package looks like in use.
     */
    protected function assertReadmeHasAQuickStart(string $root): void
    {
        $readme = (string) file_get_contents($root.'/README.md');

        $this->assertMatchesRegularExpression(
            '/^## Quick start$/m',
            $readme,
            'the README has no `## Quick start` section',
        );

        $install = strpos($readme, "\n## Install");
        $quick = strpos($readme, "\n## Quick start");
        $docs = strpos($readme, "\n".self::DOCS_INDEX_HEADING);

        if ($install !== false && $quick !== false) {
            $this->assertGreaterThan($install, $quick, '`## Quick start` must come after `## Install`');
        }

        if ($quick !== false && $docs !== false) {
            $this->assertLessThan($docs, $quick, '`## Quick start` must come before `## Documentation`');
        }
    }

    /**
     * A licence file cannot be inherited from the org `.github` repo: it is not in
     * GitHub's community-health cascade set, it is the file licence detection reads, and
     * no `.gitattributes` in the estate export-ignores it, so it ships in every dist
     * archive. Measured 2026-09-28: seven laranail packages had none, four of them
     * declaring one in `composer.json`.
     */
    protected function assertLicenceFilePresentAndAgreesWithManifest(string $root): void
    {
        $this->assertFileExists($root.'/LICENSE', 'the package ships no LICENSE file');

        $this->assertFileDoesNotExist(
            $root.'/LICENSE.md',
            'the package ships both LICENSE and LICENSE.md — one of them is the real one',
        );

        $manifest = $root.'/composer.json';

        if (! is_file($manifest)) {
            return;
        }

        $declared = json_decode((string) file_get_contents($manifest), true)['license'] ?? null;

        if (! is_string($declared)) {
            return;
        }

        $text = (string) file_get_contents($root.'/LICENSE');

        if (strcasecmp($declared, 'MIT') === 0) {
            $this->assertStringContainsStringIgnoringCase(
                'MIT',
                $text,
                'composer.json declares MIT but the LICENSE file does not say so',
            );

            return;
        }

        $this->assertStringNotContainsString(
            'Permission is hereby granted, free of charge',
            $text,
            "composer.json declares `{$declared}` but the LICENSE file carries MIT's grant",
        );
    }

    /** @return list<string> repo-relative paths, sorted */
    private function docsPages(string $root): array
    {
        $docs = $root.'/docs';

        if (! is_dir($docs)) {
            return [];
        }

        $found = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docs)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $found[] = 'docs/'.str_replace('\\', '/', substr($file->getPathname(), strlen($docs) + 1));
            }
        }

        sort($found);

        return $found;
    }

    /** @return list<string> the page's non-empty, right-trimmed lines */
    private function docsPageLines(string $root, string $page): array
    {
        $lines = explode("\n", (string) file_get_contents($root.'/'.$page));

        return array_values(array_filter(array_map(rtrim(...), $lines), static fn ($l): bool => trim($l) !== ''));
    }
}

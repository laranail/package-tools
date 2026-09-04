<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use RecursiveIteratorIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use PHPUnit\Framework\Attributes\Test;

/**
 * The docs standard, asserted rather than described.
 *
 * The rules it checks are the mechanical ones from the org standard: an H1 that
 * names the page, a one-line summary under it, a `---` rule and a relative
 * footer at the end, no decorative emoji, and no `docs/README.md` or `docs/adr/`
 * (the index is the README's Documentation section; rationale is prose in
 * architecture.md).
 *
 * These drift silently. Fourteen pages had lost the `---` rule and two had lost
 * their summary, and nothing failed - a docs tree is only ever read one page at
 * a time, so an inconsistency between pages is invisible from inside any of them.
 */
final class DocsConformanceTest extends TestCase
{
    private const FOOTER = '[← Docs index](';

    #[Test]
    public function there_are_pages_to_check(): void
    {
        // An empty glob would make every other test here vacuously true.
        $this->assertGreaterThan(20, count(self::pages()));
    }

    #[Test]
    public function every_page_opens_with_an_h1_and_a_one_line_summary(): void
    {
        foreach (self::pages() as $page) {
            $lines = $this->content($page);

            $this->assertStringStartsWith('# ', $lines[0], "$page does not open with an H1");

            // The summary is prose: not a heading, fence, list, table or quote.
            $this->assertNotEmpty($lines[1] ?? '', "$page has no summary under its H1");
            $this->assertDoesNotMatchRegularExpression(
                '/^(#|```|\||- |\* |> )/',
                $lines[1],
                "$page: the line under the H1 is [{$lines[1]}], not a one-line summary",
            );
        }
    }

    #[Test]
    public function every_page_ends_with_a_rule_and_a_footer_at_the_right_depth(): void
    {
        foreach (self::pages() as $page) {
            $lines = $this->content($page);

            // docs/x.md -> ../ ; docs/tools/x.md and docs/recipes/x.md -> ../../
            $depth = substr_count($page, '/');
            $expected = self::FOOTER . str_repeat('../', $depth) . 'README.md#documentation)';

            $this->assertSame($expected, end($lines), "$page has the wrong docs-index footer");
            $this->assertSame('---', prev($lines), "$page is missing the `---` rule before its footer");
        }
    }

    #[Test]
    public function no_page_carries_a_decorative_emoji(): void
    {
        // Semantic glyphs are fine and the footer arrow is one; status emoji are not.
        foreach (self::pages() as $page) {
            $this->assertSame(
                0,
                preg_match(
                    '/[\x{1F300}-\x{1FAFF}\x{2705}\x{274C}\x{26A0}\x{2757}\x{2B50}]/u',
                    file_get_contents(self::root() . '/' . $page),
                ),
                "$page contains a decorative emoji",
            );
        }
    }

    #[Test]
    public function the_docs_tree_has_no_index_page_and_no_adr_directory(): void
    {
        // The index is the README's Documentation section; two indexes drift.
        // Architectural rationale is prose in architecture.md, not an adr/ tree.
        $this->assertFileDoesNotExist(self::root() . '/docs/README.md');
        $this->assertDirectoryDoesNotExist(self::root() . '/docs/adr');
    }

    #[Test]
    public function the_readme_index_lists_every_page_and_only_real_ones(): void
    {
        $readme = file_get_contents(self::root() . '/README.md');

        $section = explode('## <a name="documentation"></a>Documentation', $readme, 2)[1] ?? '';
        $section = explode("\n## ", $section, 2)[0];

        $this->assertNotSame('', trim($section), 'the README has no Documentation section');

        preg_match_all('#\]\((docs/[^)\#]+\.md)\)#', $section, $m);

        $linked = array_unique($m[1]);
        $onDisk = self::pages();

        sort($linked);

        $this->assertSame(
            $onDisk,
            array_values($linked),
            'the README Documentation index and docs/ have drifted apart',
        );
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> repo-relative paths */
    private static function pages(): array
    {
        $root = self::root() . '/docs';

        $found = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $found[] = 'docs/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }

        sort($found);

        return $found;
    }

    /** @return list<string> the page's non-empty lines */
    private function content(string $page): array
    {
        $lines = explode("\n", file_get_contents(self::root() . '/' . $page));

        return array_values(array_filter(array_map('rtrim', $lines), static fn ($l) => trim($l) !== ''));
    }
}

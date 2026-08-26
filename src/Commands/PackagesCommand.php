<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Simtabi\Laranail\Package\Tools\Support\Registry\PackageRegistry;

/**
 * `php artisan laranail::package-tools.packages` — every package built on this toolkit, what each one
 * claimed, and whether any two claimed the same name.
 *
 * The last part is the reason it exists. Laravel keeps view namespaces, translation namespaces,
 * config keys and publish tags in flat global maps, so a second package claiming a key does not
 * collide loudly — it silently replaces the first, and the failure surfaces far away as a missing
 * view or the wrong file published. Nothing in the framework will tell you it happened; this will.
 */
final class PackagesCommand extends Command
{
    protected $signature = 'laranail::package-tools.packages
        {--json : Emit JSON instead of TTY output}
        {--collisions : Report only the clashes, and exit non-zero if there are any}';

    protected $description = 'List packages built on laranail/package-tools and detect name clashes.';

    public function handle(PackageRegistry $registry): int
    {
        $described = array_map($registry->describe(...), array_keys($registry->all()));
        usort($described, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        $collisions = $registry->collisions();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['packages' => $described, 'collisions' => $collisions],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return $collisions === [] ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('collisions')) {
            $this->renderPackages($described);
        }

        $this->renderCollisions($collisions);

        // Only --collisions makes a clash fatal. A plain listing is a listing: failing it would make
        // the command unusable for the thing people run it for most.
        return $this->option('collisions') && $collisions !== [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $described
     */
    private function renderPackages(array $described): void
    {
        if ($described === []) {
            $this->warn('No packages built on laranail/package-tools are registered.');

            return;
        }

        $this->table(
            ['Package', 'Version', 'Config', 'Views', 'Translations', 'Commands'],
            array_map(static fn (array $p): array => [
                $p['name'],
                $p['version'],
                $p['config'] ?? '—',
                $p['views'] ?? '—',
                $p['translations'] ?? '—',
                (string) count($p['commands']),
            ], $described),
        );

        $this->line(sprintf('  %d package(s).', count($described)));
    }

    /**
     * @param array<string, array<string, list<string>>> $collisions
     */
    private function renderCollisions(array $collisions): void
    {
        if ($collisions === []) {
            $this->info('No name clashes: every config key, view namespace, translation namespace and component prefix is claimed once.');

            return;
        }

        $this->newLine();
        $this->error('Name clashes found. The later package silently replaces the earlier one.');

        foreach ($collisions as $surface => $byName) {
            foreach ($byName as $name => $packages) {
                $this->line(sprintf('  %s  <fg=yellow>%s</>  claimed by: %s', $surface, $name, implode(', ', $packages)));
            }
        }
    }
}

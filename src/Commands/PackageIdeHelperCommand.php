<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Simtabi\Laranail\PackageTools\Services\Facade\FacadeAutoGenerator;
use Throwable;

/**
 * `php artisan package:ide-helper` — generate Facade classes from
 * `#[AsFacade]`-annotated contracts so IDEs can autocomplete the static
 * methods. Output goes to `<output>` (default: src/Facades/).
 */
final class PackageIdeHelperCommand extends Command
{
    protected $signature = 'package:ide-helper
        {--source=src : Source directory to scan (relative to base_path or absolute)}
        {--source-namespace=App\\\\ : PSR-4 root namespace for --source}
        {--output=src/Facades : Where to write generated Facade classes}
        {--facade-namespace=App\\\\Facades : Namespace for generated Facade classes}';

    protected $description = 'Generate Facade classes for #[AsFacade] contracts (laranail/package-tools).';

    public function handle(): int
    {
        $generator = new FacadeAutoGenerator;

        $source = $this->absolutise((string) $this->option('source'));
        $output = $this->absolutise((string) $this->option('output'));

        try {
            $written = $generator->generate(
                sourceDirectory: $source,
                sourceNamespace: (string) $this->option('source-namespace'),
                outputDirectory: $output,
                facadeNamespace: (string) $this->option('facade-namespace'),
            );
        } catch (Throwable $e) {
            $this->error('IDE helper generation failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($written === []) {
            $this->warn('No #[AsFacade]-annotated contracts found under: ' . $source);

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Generated ' . count($written) . ' facade(s):');
        foreach ($written as $row) {
            $this->line("  <fg=green>✔</> <fg=white;options=bold>{$row['alias']}</> → {$row['file']}");
        }
        $this->line('');

        return self::SUCCESS;
    }

    private function absolutise(string $path): string
    {
        return Str::startsWith($path, '/') ? $path : base_path($path);
    }
}

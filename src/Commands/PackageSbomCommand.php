<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Commands;

use Illuminate\Console\Command;
use Simtabi\Laranail\PackageTools\Services\Sbom\SbomGenerator;
use Throwable;

/**
 * `php artisan package:sbom` — generate a CycloneDX 1.5 JSON SBOM for the
 * current project. Pure-PHP, reads composer.json + composer.lock.
 */
final class PackageSbomCommand extends Command
{
    protected $signature = 'package:sbom
        {--output=sbom.json : Output path (relative to project root or absolute)}
        {--print : Print SBOM JSON to stdout instead of writing a file}';

    protected $description = 'Generate a CycloneDX SBOM (laranail/package-tools).';

    public function handle(): int
    {
        $generator = new SbomGenerator(base_path());

        try {
            if ($this->option('print')) {
                $this->line(json_encode(
                    $generator->generate(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ));

                return self::SUCCESS;
            }

            $output = (string) $this->option('output');
            $written = $generator->generateToFile($output);

            $this->info("CycloneDX SBOM written to: {$written}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('SBOM generation failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}

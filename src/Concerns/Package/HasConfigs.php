<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Concerns\Package;

trait HasConfigs
{
    public bool $hasConfigs = false;

    /** @var list<string> */
    public array $configFileNames = [];

    /**
     * @param string|array<int, string>|null $configFileName
     */
    public function hasConfigFile($configFileName = null): static
    {
        $configFileName ??= $this->shortName();

        $names = is_array($configFileName) ? $configFileName : [$configFileName];

        // Append + de-duplicate. Multiple hasConfigFile() calls register
        // multiple config files (the test contract); a single array-form
        // call registers all entries at once.
        foreach ($names as $name) {
            if (! in_array($name, $this->configFileNames, true)) {
                $this->configFileNames[] = $name;
            }
        }

        $this->hasConfigs = true;

        return $this;
    }
}

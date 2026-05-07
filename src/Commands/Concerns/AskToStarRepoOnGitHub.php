<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Commands\Concerns;

trait AskToStarRepoOnGitHub
{
    protected ?string $starRepo = null;

    protected bool $defaultStarAnswer = true;

    public function askToStarRepoOnGitHub($vendorSlashRepoName, bool $defaultAnswer = false): self
    {
        $this->starRepo = $vendorSlashRepoName;
        $this->defaultStarAnswer = $defaultAnswer;

        return $this;
    }

    protected function processStarRepo(): self
    {
        if ($this->starRepo && $this->confirm('Would you like to star our repo on GitHub?', $this->defaultStarAnswer)) {
            $repoUrl = "https://github.com/{$this->starRepo}";
            if (PHP_OS_FAMILY === 'Darwin') {
                exec("open {$repoUrl}");
            }
            if (PHP_OS_FAMILY === 'Windows') {
                exec("start {$repoUrl}");
            }
            if (PHP_OS_FAMILY === 'Linux') {
                exec("xdg-open {$repoUrl}");
            }
        }

        return $this;
    }
}

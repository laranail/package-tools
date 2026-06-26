<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasAboutSections
{
    /**
     * Sections to surface under `php artisan about`.
     *
     * @var list<array{label: string, data: callable}>
     */
    public array $aboutSections = [];

    /**
     * Add a section to Laravel's `about` command. `$data` is a callable that
     * returns an associative array of label => value pairs.
     */
    public function hasAboutSection(string $label, callable $data): static
    {
        $this->aboutSections[] = [
            'label' => $label,
            'data' => $data,
        ];

        return $this;
    }
}

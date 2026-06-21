<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Database\Seeder;

/**
 * In-memory store of per-package seeder configurations.
 *
 * Each entry is keyed by an opaque label (typically the consumer
 * package's namespace) and carries the list of `Seeder` classes that
 * package contributes plus any per-package execution options.
 */
final class SeederRegistry
{
    /**
     * @var array<string, array{seeders: list<class-string<Seeder>>, namespace: ?string, options: array<string, mixed>}>
     */
    private array $configurations = [];

    /**
     * Register (or replace) a configuration for `$key`.
     *
     * @param list<class-string<Seeder>> $seeders
     * @param array<string, mixed> $options
     */
    public function register(string $key, array $seeders, ?string $namespace = null, array $options = []): self
    {
        $this->configurations[$key] = [
            'seeders' => $seeders,
            'namespace' => $namespace,
            'options' => $options,
        ];

        return $this;
    }

    public function forget(string $key): self
    {
        unset($this->configurations[$key]);

        return $this;
    }

    public function clear(): self
    {
        $this->configurations = [];

        return $this;
    }

    /**
     * @return array{seeders: list<class-string<Seeder>>, namespace: ?string, options: array<string, mixed>}|null
     */
    public function get(string $key): ?array
    {
        return $this->configurations[$key] ?? null;
    }

    /**
     * @return array<string, array{seeders: list<class-string<Seeder>>, namespace: ?string, options: array<string, mixed>}>
     */
    public function all(): array
    {
        return $this->configurations;
    }

    public function isEmpty(): bool
    {
        return $this->configurations === [];
    }

    public function count(): int
    {
        return count($this->configurations);
    }
}

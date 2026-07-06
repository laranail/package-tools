<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Seeder;

/**
 * one package's registered seeder bundle: typed fields and fluent setters
 * instead of magic option-string keys. options are scoped to the bundle —
 * one package's fk/event/parameter choices never leak into another's run.
 *
 * @implements Arrayable<string, mixed>
 */
final class SeederBundle implements Arrayable
{
    private ?string $namespace = null;

    private int $priority = 0;

    private bool $disableForeignKeyChecks = true;

    private bool $fireEvents = false;

    /** @var array<string, mixed> */
    private array $parameters = [];

    /**
     * @param list<class-string<Seeder>> $seeders
     */
    private function __construct(
        private readonly string $key,
        private readonly array $seeders,
    ) {}

    /**
     * @param list<class-string<Seeder>> $seeders execution order = array order
     */
    public static function make(string $key, array $seeders): self
    {
        return new self($key, array_values($seeders));
    }

    /**
     * build from the legacy string-keyed options shape (the escape-hatch
     * path used by SeederRegistry::register()).
     *
     * @param list<class-string<Seeder>> $seeders
     * @param array<string, mixed> $options
     */
    public static function fromOptions(string $key, array $seeders, ?string $namespace, array $options): self
    {
        $bundle = self::make($key, $seeders)->inNamespace($namespace);

        $bundle->disableForeignKeyChecks = (bool) ($options['disable_foreign_key_checks'] ?? true);
        $bundle->fireEvents = (bool) ($options['fire_events'] ?? false);
        $bundle->parameters = is_array($options['parameters'] ?? null) ? $options['parameters'] : [];
        $bundle->priority = (int) ($options['priority'] ?? 0);

        return $bundle;
    }

    public function inNamespace(?string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * lower runs first; ties keep registration order.
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function withoutForeignKeyChecks(bool $disable = true): self
    {
        $this->disableForeignKeyChecks = $disable;

        return $this;
    }

    public function firesEvents(bool $fire = true): self
    {
        $this->fireEvents = $fire;

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters passed to seeders accepting ctor args
     */
    public function parameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return list<class-string<Seeder>>
     */
    public function seeders(): array
    {
        return $this->seeders;
    }

    public function namespace(): ?string
    {
        return $this->namespace;
    }

    public function priorityValue(): int
    {
        return $this->priority;
    }

    public function disablesForeignKeyChecks(): bool
    {
        return $this->disableForeignKeyChecks;
    }

    public function shouldFireEvents(): bool
    {
        return $this->fireEvents;
    }

    /**
     * @return array<string, mixed>
     */
    public function parametersValue(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'seeders' => $this->seeders,
            'namespace' => $this->namespace,
            'priority' => $this->priority,
            'disable_foreign_key_checks' => $this->disableForeignKeyChecks,
            'fire_events' => $this->fireEvents,
            'parameters' => $this->parameters,
        ];
    }
}

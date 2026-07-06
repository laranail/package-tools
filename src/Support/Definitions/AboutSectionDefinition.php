<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;

/**
 * a fluent `php artisan about` section: fields are declared one by one as
 * scalars or per-field lazy closures (evaluated only when the about command
 * actually runs — no mega-closure closing over everything), optionally
 * gated on config through the shared ConfigGate.
 *
 * @implements Arrayable<string, mixed>
 */
final class AboutSectionDefinition implements Arrayable, Jsonable, JsonSerializable
{
    /** @var array<string, Closure|bool|float|int|string> */
    private array $fields = [];

    /** @var list<Closure> whole-array lazy sources, merged at resolve time */
    private array $bulkSources = [];

    private ?ConfigGate $gate = null;

    private function __construct(
        private readonly string $label,
    ) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    /**
     * one field: a scalar shown as-is, or a closure resolved when the
     * about command runs.
     */
    public function field(string $name, Closure|bool|float|int|string $value): self
    {
        $this->fields[$name] = $value;

        return $this;
    }

    /**
     * @param array<string, Closure|bool|float|int|string> $fields
     */
    public function fields(array $fields): self
    {
        foreach ($fields as $name => $value) {
            // php silently turns numeric-string keys into ints; cast back so
            // a '2026' field name never hits the string-typed field() as int
            $this->field((string) $name, $value);
        }

        return $this;
    }

    /**
     * a whole-array lazy source (`fn (): array => [...]`); merged before
     * individual fields, so explicit field() calls win on name collisions.
     */
    public function fieldsUsing(Closure $source): self
    {
        $this->bulkSources[] = $source;

        return $this;
    }

    public function whenConfig(string $key, bool $default = true): self
    {
        $this->gate = ConfigGate::make($key, $default)->truthy();

        return $this;
    }

    public function whenConfigNotNull(string $key): self
    {
        $this->gate = ConfigGate::make($key)->notNull();

        return $this;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function shouldDisplay(): bool
    {
        return ! $this->gate instanceof ConfigGate || $this->gate->passes();
    }

    /**
     * evaluate every source and field; called by the about command only.
     *
     * @return array<string, string>
     */
    public function resolve(): array
    {
        $resolved = [];

        foreach ($this->bulkSources as $source) {
            foreach ($source() as $name => $value) {
                $resolved[(string) $name] = $this->stringify($value);
            }
        }

        foreach ($this->fields as $name => $value) {
            $resolved[$name] = $this->stringify($value instanceof Closure ? $value() : $value);
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'fields' => array_map(
                static fn (Closure|bool|float|int|string $value): bool|float|int|string => $value instanceof Closure ? 'closure' : $value,
                $this->fields,
            ),
            'bulk_sources' => count($this->bulkSources),
            'gate' => $this->gate?->toArray(),
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_THROW_ON_ERROR),
        };
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Seeder;
use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Enums\Environment;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederPathDiscoverer;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;

use function Illuminate\Support\enum_value;

/**
 * a package's seeder bundle for db:seed-time execution: an explicit,
 * order-guaranteed list, or discovery over a directory — either way
 * combinable with an ignore list. gating uses the shared ConfigGate.
 *
 * @implements Arrayable<string, mixed>
 */
final class AutoSeederDefinition implements Arrayable, Jsonable, JsonSerializable
{
    /** @var list<class-string<Seeder>> */
    private array $seeders = [];

    /** @var list<class-string<Seeder>> */
    private array $ignored = [];

    private ?string $discoveryPath = null;

    private bool $recursiveDiscovery = false;

    private ?string $namespace = null;

    private ?ConfigGate $gate = null;

    private ?int $priority = null;

    private bool $autorun = false;

    private bool $stopOnFailure = false;

    /** @var list<string> */
    private array $autorunEnvironments = [];

    /** @var array<string, mixed> */
    private array $options = [];

    private function __construct(
        private readonly string $key,
    ) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    /**
     * explicit list — execution order is the array order. empty (or never
     * called) switches to discovery mode over the package's seeders dir.
     *
     * @param list<class-string<Seeder>> $seeders
     */
    public function seeders(array $seeders = []): self
    {
        $this->seeders = array_values($seeders);

        return $this;
    }

    /**
     * discovery-source override; without it, discovery falls back to the
     * package's database/seeders directory at boot. `$recursive` descends
     * into nested directories.
     */
    public function discoverIn(string $path, bool $recursive = false): self
    {
        $this->discoveryPath = $path;
        $this->recursiveDiscovery = $recursive;

        return $this;
    }

    /**
     * exclusion, applied to both explicit and discovered lists.
     *
     * @param list<class-string<Seeder>> $seeders
     */
    public function ignoreSeeders(array $seeders): self
    {
        $this->ignored = array_values($seeders);

        return $this;
    }

    public function inNamespace(?string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Append seeders to the explicit list without replacing it — the
     * incremental counterpart to seeders(). Duplicates are ignored.
     */
    public function addSeeders(string ...$seeders): self
    {
        $this->seeders = array_values(array_unique([...$this->seeders, ...$seeders]));

        return $this;
    }

    /**
     * Opt in to automatic execution after a successful `php artisan
     * migrate` run. Seeders NEVER run on their own without this flag; even
     * with it, the SeederAutorun safety gates apply (console-only, config
     * kill-switches, environment gate, once per process).
     */
    public function autorunAfterMigrations(bool $autorun = true): self
    {
        $this->autorun = $autorun;

        return $this;
    }

    /**
     * Alias of autorunAfterMigrations().
     */
    public function autorunNow(bool $autorun = true): self
    {
        return $this->autorunAfterMigrations($autorun);
    }

    /**
     * Restrict autorun to these environments. A non-empty list REPLACES
     * the production config gate for this bundle — the author consciously
     * chose where it may run.
     */
    public function autorunInEnvironments(Environment|BackedEnum|string ...$environments): self
    {
        $this->autorunEnvironments = array_values(array_map(
            static fn (Environment|BackedEnum|string $environment): string => (string) enum_value($environment),
            $environments,
        ));

        return $this;
    }

    /**
     * Skip the bundle's remaining seeders after the first failure.
     */
    public function stopOnFailure(bool $stop = true): self
    {
        $this->stopOnFailure = $stop;

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

    /**
     * lower runs first; ties keep registration order.
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * legacy string-keyed SeederRegistry options passthrough.
     *
     * @param array<string, mixed> $options
     */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function namespace(): ?string
    {
        return $this->namespace;
    }

    public function shouldRegister(): bool
    {
        return ! $this->gate instanceof ConfigGate || $this->gate->passes();
    }

    public function priorityValue(): int
    {
        return $this->priority ?? 0;
    }

    /**
     * Whether priority() was explicitly called — lets the boot merge keep
     * an options(['priority' => …]) value when the fluent one was never set.
     */
    public function hasExplicitPriority(): bool
    {
        return $this->priority !== null;
    }

    public function isAutorun(): bool
    {
        return $this->autorun;
    }

    public function shouldStopOnFailure(): bool
    {
        return $this->stopOnFailure;
    }

    /**
     * @return list<string>
     */
    public function autorunEnvironmentsValue(): array
    {
        return $this->autorunEnvironments;
    }

    /**
     * @return array<string, mixed>
     */
    public function optionsValue(): array
    {
        return $this->options;
    }

    /**
     * boot-time resolution: explicit list, else discovery over the given
     * (or default) directory — minus the ignore list. an empty result
     * registers nothing, which is not an error.
     *
     * @return list<class-string<Seeder>>
     */
    public function resolveSeeders(string $defaultDiscoveryPath): array
    {
        if ($this->seeders !== []) {
            // dedupe the explicit list: a seeder listed twice must not run
            // twice (first occurrence keeps its position)
            $seeders = array_values(array_unique($this->seeders));
        } else {
            $path = $this->discoveryPath ?? $defaultDiscoveryPath;

            // a package without a seeders directory has nothing to discover
            // — not a boot error (the discoverer itself stays strict for
            // direct callers)
            $seeders = is_dir($path) ? $this->discoverer()->discover($path, $this->recursiveDiscovery) : [];
        }

        return array_values(array_diff($seeders, $this->ignored));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'seeders' => $this->seeders,
            'ignored' => $this->ignored,
            'discovery_path' => $this->discoveryPath,
            'namespace' => $this->namespace,
            'gate' => $this->gate?->toArray(),
            'priority' => $this->priorityValue(),
            'autorun' => $this->autorun,
            'autorun_environments' => $this->autorunEnvironments,
            'stop_on_failure' => $this->stopOnFailure,
            'options' => $this->options,
        ];
    }

    private function discoverer(): SeederPathDiscoverer
    {
        if (function_exists('app') && app()->bound(SeederPathDiscoverer::class)) {
            return app(SeederPathDiscoverer::class);
        }

        return new SeederPathDiscoverer;
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
}

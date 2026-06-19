# Runtime services

Container-bound services that consumers resolve directly — system
inspection, HTTP-client option building, and an install-time error bag.

Three runtime services are bound automatically by
`LaranailToolsServiceProvider` (no consumer wiring), plus two consumer
traits that proxy to them.

| Binding | Lifetime | Implementation |
|---|---|---|
| `SystemServiceInterface` | request-scoped (`bind`) | `Services\System\SystemService` |
| `HttpConfigurationServiceInterface` | singleton | `Services\Http\HttpConfigurationService` |
| `ErrorStorageServiceInterface` | per-resolution (`bind`) | `Support\ErrorStorage\ErrorStorageService` |

## SystemService

`Simtabi\Laranail\PackageTools\Services\System\SystemService` is a
read-only inspector for the host PHP / Laravel / server context (no
shell-outs, no network calls). Bound request-scoped because its output
depends on `$_SERVER`. Resolve via `SystemServiceInterface`.

| Method | Returns | Notes |
|---|---|---|
| `getComposerArray()` | `array<string,mixed>` | The app's `composer.json` (resolved from `Application::basePath()`); `[]` when missing/unparseable or no app context. |
| `getPackagesAndDependencies(array $packages)` | `array<string, array{version, type}>` | Declared constraints for the named packages; `type` is `require` or `require-dev`; packages not present are omitted. |
| `getSystemEnv()` | `array<string,mixed>` | PHP version/OS, OS family, request-time server vars, Laravel version and environment. |
| `getServerEnv()` | `array<string,mixed>` | PHP SAPI, loaded extensions, ini settings, timezone, disk free/total space. |
| `getOsFamily()` | `'windows'\|'macos'\|'linux'\|'bsd'\|'unknown'` | From `PHP_OS_FAMILY`. |
| `isSslInstalled()` | `bool` | TLS detection via `HTTPS`, `REQUEST_SCHEME`, `HTTP_X_FORWARDED_PROTO`, or port 443; conservative (`false` on CLI). |

Intended for install commands, doctor checks, and diagnostic tooling —
not production hot-paths.

```php
use Simtabi\Laranail\PackageTools\Services\System\Contracts\SystemServiceInterface;

final class EnvironmentDoctorCheck implements DoctorCheck
{
    public function __construct(private SystemServiceInterface $system) {}

    public function run(): DoctorResult
    {
        return $this->system->isSslInstalled()
            ? DoctorResult::pass('TLS is available.')
            : DoctorResult::warn('No TLS detected for this request.');
    }

    // name(), description() …
}
```

Inject `SystemServiceInterface` (not the concrete class) so the
request-scoped binding is honoured.

## HttpConfigurationService

`Simtabi\Laranail\PackageTools\Services\Http\HttpConfigurationService`
(contract `…\Http\Contracts\HttpConfigurationServiceInterface`) is a
fluent builder for HTTP-client option arrays. It is vendor-neutral — it
pulls in no HTTP client of its own — and its output array is keyed
compatibly with both Guzzle and Laravel's `Http::withOptions()`.

Defaults are read from environment variables on construction; an unset or
empty variable falls back to the default. Every value can also be set
fluently in code (constructor args or setters), which overrides the env
default.

| Variable | Type | Default | Setter / getter |
|---|---|---|---|
| `PKG_HTTP_PERSIST_CONNECTION` | bool | `true` | `setPersistConnection()` / `isPersistConnection()` |
| `PKG_HTTP_REQUEST_TIMEOUT` | int (seconds, `>= 0`) | `60` | `setRequestTimeout()` / `getRequestTimeout()` |
| `PKG_HTTP_MAX_RETRIES` | int (`>= 0`) | `10` | `setMaxRetries()` / `getMaxRetries()` |
| `PKG_HTTP_CACHE_TTL` | int (seconds, `>= 0`) | `10` | `setCacheTtl()` / `getCacheTtl()` |
| `PKG_HTTP_BASE_URI` | string | `null` | `setBaseUri()` / `getBaseUri()` |
| `PKG_HTTP_PROXY` | string | `null` | `setProxy()` / `getProxy()` |

The three integer setters and the constructor reject negative values
(`InvalidArgumentException`). `setBaseUri()` / `setProxy()` trim their
input.

`toGuzzleConfig()` serialises the current configuration:

```php
[
    'persist'   => bool,
    'timeout'   => int,
    'retry'     => ['max' => int],
    'cache_ttl' => int,
    // 'base_uri' and 'proxy' only when set and non-empty
]
```

### HasGuzzleConfig

`Simtabi\Laranail\PackageTools\Support\Concerns\HasGuzzleConfig` is a
thin accessor trait. `httpConfig()` resolves the singleton
`HttpConfigurationServiceInterface` from the container, so a host class
(provider, install command, job) can grab a preconfigured options array
without wiring a binding:

```php
use Simtabi\Laranail\PackageTools\Support\Concerns\HasGuzzleConfig;

final class FetchUsersJob
{
    use HasGuzzleConfig;

    public function handle(): void
    {
        $options = $this->httpConfig()->toGuzzleConfig();
        Http::withOptions($options)->get('https://api.example.com/users');
    }
}
```

## ErrorStorageService

`Simtabi\Laranail\PackageTools\Support\ErrorStorage\ErrorStorageService`
(contract `…\ErrorStorage\Contracts\ErrorStorageServiceInterface`) is an
in-memory key/message error bag. It has no eviction policy and is not
safe to share across long-lived processes — hence the per-resolution
binding, so each install command gets a clean bag.

Adding the same key twice promotes the value to an ordered list of
messages.

| Method | Returns | Purpose |
|---|---|---|
| `ErrorStorageService::create()` | `self` | New empty bag. |
| `ErrorStorageService::withErrors(array\|string $errors)` | `self` | New bag seeded with errors. |
| `setErrors(array\|string $errors)` | `static` | Merge errors in (a bare string is parked under the `_` key). |
| `addError(string $key, string $message)` | `static` | Add a message under a key (promotes to a list on repeat). |
| `getErrors(?string $key = null)` | `array` | All errors, or `Arr::wrap`-ed value for a key. |
| `hasErrors()` | `bool` | Bag is non-empty. |
| `getErrorCount()` | `int` | Number of keys. |
| `getFirstError()` | `?string` | First message (resolving a list to its first element). |
| `clearErrors()` | `static` | Empty the bag. |

### HasErrorStorage

`Simtabi\Laranail\PackageTools\Support\Concerns\HasErrorStorage` proxies
a host class to the container-bound `ErrorStorageService`, keeping
install commands and providers free of resolve-and-delegate boilerplate.
It exposes `setErrors()`, `getErrors()`, `hasErrors()`, `clearErrors()`,
`addError()`, `getErrorCount()`, and `getFirstError()`.

The resolved bag is cached per host instance (`errorStorage()`) so state
survives across calls despite the per-resolution binding; tests can swap
the implementation by binding a singleton before constructing the host.

```php
use Simtabi\Laranail\PackageTools\Support\Concerns\HasErrorStorage;

final class HelloInstaller
{
    use HasErrorStorage;

    public function run(): void
    {
        if (! file_exists(config_path('hello.php'))) {
            $this->addError('config', 'config/hello.php is not published.');
        }

        if ($this->hasErrors()) {
            // Surface getFirstError() / getErrors() to the operator.
        }
    }
}
```

## See also

- [installation.md](../installation.md) — how the three bindings are
  registered by `LaranailToolsServiceProvider`.
- [configuration.md](../configuration.md#runtime-environment-variables) —
  the `PKG_HTTP_*` env table, mirrored above.
- [services.md](../services.md) — the `System`, `Http`, and `ErrorStorage`
  classes in the wider service catalogue.
- [examples/Jobs/SyncGreetingsJob.php](../examples/Jobs/SyncGreetingsJob.php)
  — a job using `HasGuzzleConfig` and `HasErrorStorage` and resolving all
  three runtime service contracts.

[← Docs index](../../README.md#documentation)

<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a job using the runtime services and consumer traits.
|------------------------------------------------------------------------------
| Two traits keep this free of resolve-and-delegate boilerplate:
|   HasGuzzleConfig   -> httpConfig() yields the container's HTTP config
|                        service; toGuzzleConfig() hands back a vendor-neutral
|                        options array to spread into Http::withOptions().
|   HasErrorStorage   -> addError()/hasErrors()/getErrors() collect non-fatal
|                        problems so the whole batch can finish before they are
|                        surfaced, instead of throwing on the first failure.
|
| It also resolves the three runtime service contracts straight from the
| container to show their public surface.
*/

namespace Acme\Hello\Jobs;

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Package\Tools\Services\Http\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Package\Tools\Services\System\Contracts\SystemServiceInterface;
use Simtabi\Laranail\Package\Tools\Support\Concerns\HasErrorStorage;
use Simtabi\Laranail\Package\Tools\Support\Concerns\HasGuzzleConfig;
use Simtabi\Laranail\Package\Tools\Support\ErrorStorage\Contracts\ErrorStorageServiceInterface;

final class SyncGreetingsJob
{
    use HasErrorStorage;
    use HasGuzzleConfig;

    /**
     * @param list<string> $sources Remote endpoints to pull greetings from.
     */
    public function __construct(private readonly array $sources) {}

    public function handle(
        SystemServiceInterface $system,
        ErrorStorageServiceInterface $errorStorage,
    ): void {
        // Skip TLS-only endpoints on a host without SSL (CLI returns false).
        if (! $system->isSslInstalled() && $this->sources !== []) {
            $errorStorage->addError('tls', 'No TLS context detected; skipping https sources.');
        }

        // Build a preconfigured options array via the fluent HTTP config service.
        $options = $this->httpConfig()
            ->setRequestTimeout(15)
            ->setMaxRetries(2)
            ->setBaseUri('https://api.example.com')
            ->toGuzzleConfig();

        foreach ($this->sources as $source) {
            $response = Http::withOptions($options)->get($source);

            if ($response->failed()) {
                // Collect rather than throw, so one bad source does not abort
                // the rest of the batch.
                $this->addError($source, "Request failed with status {$response->status()}.");

                continue;
            }

            // ... persist $response->json() ...
        }

        // Surface everything that went wrong at the end.
        if ($this->hasErrors()) {
            logger()->warning('hello: sync finished with errors', [
                'count' => $this->getErrorCount(),
                'first' => $this->getFirstError(),
                'all' => $this->getErrors(),
            ]);
        }
    }

    /**
     * Reading config back out is handy in tests and diagnostics.
     */
    public function describeHttpConfig(HttpConfigurationServiceInterface $http): string
    {
        return sprintf('timeout=%ds retries=%d', $http->getRequestTimeout(), $http->getMaxRetries());
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

/**
 * declarative named rate limiters. limiter closures run per request, so
 * config reads inside them are already lazy — no extra sugar needed.
 */
trait HasRateLimiters
{
    /** @var array<string, Closure> */
    protected array $rateLimiters = [];

    public function registerRateLimiter(string $name, Closure $limiter): static
    {
        $this->rateLimiters[$name] = $limiter;

        return $this;
    }

    /**
     * @param array<string, Closure> $limiters
     */
    public function registerRateLimiters(array $limiters): static
    {
        foreach ($limiters as $name => $limiter) {
            $this->registerRateLimiter($name, $limiter);
        }

        return $this;
    }

    public function bootPackageRateLimiters(): void
    {
        foreach ($this->rateLimiters as $name => $limiter) {
            RateLimiter::for($name, $limiter);
        }
    }

    /**
     * @return array<string, Closure>
     */
    public function getRateLimiters(): array
    {
        return $this->rateLimiters;
    }
}

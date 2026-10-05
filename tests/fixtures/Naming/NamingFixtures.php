<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\Naming;

use Closure;
use Illuminate\View\Component;
use Illuminate\Cache\RateLimiting\Limit;
use Simtabi\Laranail\Package\Tools\Commands\Command;

/**
 * A stand-in package for the shared naming assertions: every class here is "owned" by the scope
 * `laranail/naming-demo` with owner namespace Simtabi\Laranail\Package\Tools\Tests\Fixtures\Naming\.
 */
final class DemoController
{
    public function show(): string
    {
        return 'ok';
    }
}

final class DemoMiddleware
{
    public function handle(mixed $request, Closure $next): mixed
    {
        return $next($request);
    }
}

final class DemoCommand extends Command
{
    protected $signature = 'laranail::naming-demo.run';

    protected $aliases = ['naming-demo:run'];

    public function handle(): int
    {
        return self::SUCCESS;
    }
}

final class DemoComponent extends Component
{
    public function render(): string
    {
        return 'component';
    }
}

final class DemoService {}

/** Closures created here carry this class as their scope, which is how ownership is read. */
final class DemoRegistrar
{
    public static function limiter(): Closure
    {
        return static fn (): Limit => Limit::none();
    }

    public static function ability(): Closure
    {
        return static fn (): bool => true;
    }

    public static function factory(): Closure
    {
        return static fn (): DemoService => new DemoService;
    }
}

/** The shape of Livewire 4's component finder, as far as the reader looks. */
final class FakeLivewireFinder
{
    /** @var array<string, class-string> */
    private array $classComponents = [];

    /** @param class-string $class */
    public function register(string $name, string $class): void
    {
        $this->classComponents[$name] = $class;
    }
}

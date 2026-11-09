<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoStateLeak;

uses(AssertsNoStateLeak::class);

/** Holds the identity a middleware would set for the current request. */
final class StateLeakTenant
{
    public string $id = '';
}

/** Reads the tenant once, at construction, the way a service with a captured dependency does. */
final readonly class StateLeakReport
{
    public string $title;

    public function __construct(StateLeakTenant $tenant)
    {
        $this->title = "Report for {$tenant->id}";
    }
}

/** One request: the middleware sets the tenant, the package builds its report. */
function stateLeakRequest(string $marker): string
{
    app(StateLeakTenant::class)->id = $marker;

    return app(StateLeakReport::class)->title;
}

it('passes when request-derived services are scoped', function (): void {
    $this->app->scoped(StateLeakTenant::class);
    $this->app->scoped(StateLeakReport::class);

    $this->assertNoStateLeaksBetweenRequests(stateLeakRequest(...));
});

it('fails when a singleton captures the first request', function (): void {
    $this->app->scoped(StateLeakTenant::class);
    $this->app->singleton(StateLeakReport::class);

    expect(fn () => $this->assertNoStateLeaksBetweenRequests(stateLeakRequest(...)))
        ->toThrow(AssertionFailedError::class, 'state survived from one request into the next');
});

it('fails when the callback never observes its marker, instead of passing vacuously', function (): void {
    expect(fn () => $this->assertNoStateLeaksBetweenRequests(static fn (string $marker): string => 'constant'))
        ->toThrow(AssertionFailedError::class, 'never observed its input');
});

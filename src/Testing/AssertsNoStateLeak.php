<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use Illuminate\Contracts\Foundation\Application;

/**
 * Proves a package keeps nothing from one request into the next, the way a long-running worker
 * (Octane on FrankenPHP, Swoole or RoadRunner) would expose it.
 *
 * Octane boots the application once per worker and, between requests, swaps in the new request
 * and flushes scoped instances. A singleton or static built from the first request's user,
 * tenant or input keeps serving it to every later caller. Testbench rebuilds the whole container
 * for every test, so an ordinary test never sees two requests in one container, and the leak
 * only shows in production. This assertion runs two requests in one container, with the same
 * flush in between:
 *
 * ```php
 * uses(AssertsNoStateLeak::class);
 *
 * it('keeps nothing from one request into the next', function (): void {
 *     $this->assertNoStateLeaksBetweenRequests(function (string $marker): mixed {
 *         app(CurrentTenant::class)->set($marker);    // what a middleware would do
 *
 *         return app(TenantReport::class)->title();    // what the package hands back
 *     });
 * });
 * ```
 *
 * The callback is one request: it receives a marker unique to that request, feeds it in as the
 * identity (user, tenant, header, input), and returns whatever the package exposes afterwards.
 * The first request's marker must appear in the first result, or the callback never observed its
 * input and the assertion would pass for any package; it must not appear in the second.
 *
 * Use it from a Testbench test case such as {@see IsolatedTestCase}.
 */
trait AssertsNoStateLeak
{
    /**
     * @param callable(string): mixed $request One request: it takes the request's marker and
     *                                         returns what the package exposes afterwards.
     */
    protected function assertNoStateLeaksBetweenRequests(callable $request): void
    {
        /** @var Application $app */
        $app = $this->app;

        StateLeaks::assertNone($app, $request);
    }
}

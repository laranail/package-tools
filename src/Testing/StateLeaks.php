<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;
use Illuminate\Contracts\Foundation\Application;

/**
 * Runs two requests in one container and asserts nothing of the first reaches the second: the
 * logic behind {@see AssertsNoStateLeak}, kept in a class so static analysis reads it.
 */
final class StateLeaks
{
    /**
     * @param callable(string): mixed $request One request: it takes the request's marker and
     *                                         returns what the package exposes afterwards.
     */
    public static function assertNone(Application $app, callable $request): void
    {
        $first = 'first-request-' . bin2hex(random_bytes(6));
        $second = 'second-request-' . bin2hex(random_bytes(6));

        self::startSimulatedRequest($app);
        $seen = self::flatten($request($first));

        Assert::assertStringContainsString($first, $seen, sprintf(
            'The first request returned nothing carrying its own marker (%s), so the callback never '
            . 'observed its input and this assertion would pass for any package. Feed the marker in '
            . 'as the identity and return what the package exposes.',
            $first,
        ));

        self::startSimulatedRequest($app);
        $later = self::flatten($request($second));

        Assert::assertStringNotContainsString($first, $later, sprintf(
            'The second request still exposes the first request\'s marker (%s), so state survived '
            . 'from one request into the next. A long-running worker would serve it to the next '
            . 'caller. Bind request-derived services with scoped(), not singleton(), and keep them out '
            . "of statics.\nSecond request returned: %s",
            $first,
            $later,
        ));
    }

    private static function flatten(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }

        return print_r($value, true);
    }

    /**
     * What Octane does between two requests on one worker: flush scoped instances and bind a
     * fresh request. Singletons and statics are deliberately left alone.
     */
    private static function startSimulatedRequest(Application $app): void
    {
        $app->forgetScopedInstances();
        $app->instance('request', Request::create('/'));
    }
}

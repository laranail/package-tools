<?php

declare(strict_types=1);

use Psr\Log\AbstractLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\RouteCollection;
use Simtabi\Laranail\Package\Tools\Enums\DeprecationNotice;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Simtabi\Laranail\Package\Tools\Support\Routing\BareRouteNameAliases;

/**
 * The shared fallback that keeps a package's deprecated bare route names resolving after they moved
 * to vendor-scoped ones. It replaces five hand-rolled copies (error-pages, env-kit-webui,
 * db-console-webui, installer-web, authkit-preset), so every behaviour any of them had is pinned
 * here, plus the three defects two of them shipped: an unchecked previous result (a TypeError under
 * strict_types), a warning per call rather than per name, and reading UrlGenerator::$routes.
 */
beforeEach(function (): void {
    BareRouteNameAliases::forgetWarnings();

    Route::get('/demo/page', fn (): string => 'page')->name('laranail-demo.page');
    Route::get('/demo/item/{item}', fn (string $item): string => $item)->name('laranail-demo.item.show');
    Route::get('/auth/login', fn (): string => 'login')->name('laranail-auth.login');
    Route::get('/api/login', fn (): string => 'api')->name('laranail-auth-api.login');
    Route::get('/app/own', fn (): string => 'own')->name('demo.own');

    app('router')->getRoutes()->refreshNameLookups();
});

/**
 * @return list<string> the E_USER_DEPRECATED messages raised while $callback ran
 */
function collectDeprecations(callable $callback): array
{
    $messages = [];

    set_error_handler(static function (int $errno, string $message) use (&$messages): bool {
        $messages[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $messages;
}

function installDemoAliases(DeprecationNotice $notice = DeprecationNotice::TriggerError, mixed $logger = null): BareRouteNameAliases
{
    return BareRouteNameAliases::install(
        router: app('router'),
        url: app('url'),
        package: 'laranail/demo',
        map: ['demo-legacy-page' => 'laranail-demo.page'],
        prefixes: ['demo.' => 'laranail-demo.'],
        notice: $notice,
        logger: $logger,
    );
}

it('resolves a mapped bare name and a prefixed one to the scoped routes', function (): void {
    installDemoAliases();

    $messages = collectDeprecations(function (): void {
        expect(route('demo-legacy-page'))->toBe(route('laranail-demo.page'))
            ->and(route('demo.item.show', ['item' => 'x']))->toBe(route('laranail-demo.item.show', ['item' => 'x']))
            ->and(route('demo.page', [], false))->toBe('/demo/page');
    });

    expect($messages)->toHaveCount(3)
        ->and($messages[0])->toContain('[demo-legacy-page]')
        ->and($messages[0])->toContain('[laranail-demo.page]')
        ->and($messages[0])->toContain('laranail/demo')
        ->and($messages[0])->toContain('next minor after 0.1');
});

it('never shadows a route the application defines under the bare name', function (): void {
    installDemoAliases();

    // demo.own is a real route; the resolver is consulted only for a MISSING name.
    $messages = collectDeprecations(function (): void {
        expect(route('demo.own'))->toEndWith('/app/own');
    });

    expect($messages)->toBe([]);
});

it('leaves an unknown name missing', function (): void {
    installDemoAliases();

    route('demo.nothing-here');
})->throws(RouteNotFoundException::class, 'demo.nothing-here');

it('warns once per name per process, not once per call', function (): void {
    installDemoAliases();

    $messages = collectDeprecations(function (): void {
        foreach (range(1, 5) as $ignored) {
            route('demo.page');
            route('demo-legacy-page');
        }
    });

    expect($messages)->toHaveCount(2);
});

it('logs once instead, when asked to', function (): void {
    $logger = new class extends AbstractLogger
    {
        /** @var list<array{string, string}> */
        public array $records = [];

        public function log($level, Stringable|string $message, array $context = []): void
        {
            $this->records[] = [(string) $level, (string) $message];
        }
    };

    installDemoAliases(DeprecationNotice::Log, fn (): object => $logger);

    $messages = collectDeprecations(function (): void {
        route('demo.page');
        route('demo.page');
    });

    expect($messages)->toBe([])
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0][0])->toBe('warning')
        ->and($logger->records[0][1])->toContain('[laranail-demo.page]');
});

it('stays silent when asked to', function (): void {
    installDemoAliases(DeprecationNotice::None);

    $messages = collectDeprecations(function (): void {
        expect(route('demo.page'))->toBe(route('laranail-demo.page'));
    });

    expect($messages)->toBe([]);
});

it('refuses a log notice with no logger to write to', function (): void {
    installDemoAliases(DeprecationNotice::Log);
})->throws(InvalidArgumentException::class, 'logger');

it('refuses an installation that maps nothing', function (): void {
    BareRouteNameAliases::install(app('router'), app('url'), 'laranail/demo');
})->throws(InvalidArgumentException::class, 'nothing');

it('refuses a prefix pair with no scoped prefix', function (): void {
    BareRouteNameAliases::install(app('router'), app('url'), 'laranail/demo', prefixes: ['demo.' => '']);
})->throws(InvalidArgumentException::class);

it('reconciles an empty bare prefix the way authkit-preset does', function (): void {
    // authkit-preset maps every missing name onto its web prefix, and `api.*` onto the API prefix
    // without that segment. Order is the caller's: the first candidate that exists wins.
    BareRouteNameAliases::install(
        router: app('router'),
        url: app('url'),
        package: 'laranail/authkit-preset',
        prefixes: ['api.' => 'laranail-auth-api.', '' => 'laranail-auth.'],
        notice: DeprecationNotice::None,
    );

    expect(route('login'))->toBe(route('laranail-auth.login'))
        ->and(route('api.login'))->toBe(route('laranail-auth-api.login'));
});

it('does not prefix a name that already carries the scoped prefix', function (): void {
    BareRouteNameAliases::install(app('router'), app('url'), 'laranail/authkit-preset', prefixes: ['' => 'laranail-auth.']);

    Route::get('/auth/laranail-auth.weird', fn (): string => 'no')->name('laranail-auth.laranail-auth.weird');
    app('router')->getRoutes()->refreshNameLookups();

    route('laranail-auth.weird');
})->throws(RouteNotFoundException::class);

it('delegates every other name to a resolver installed before it', function (): void {
    app('url')->resolveMissingNamedRoutesUsing(
        fn (string $name): ?string => $name === 'someone-else.page' ? 'http://localhost/elsewhere' : null,
    );

    installDemoAliases();

    expect(route('someone-else.page'))->toBe('http://localhost/elsewhere')
        ->and(collectDeprecations(fn (): string => route('demo.page')))->toHaveCount(1);
});

it('chains two installations so neither discards the other', function (): void {
    installDemoAliases(DeprecationNotice::None);

    BareRouteNameAliases::install(app('router'), app('url'), 'laranail/authkit-preset', prefixes: ['legacy-auth.' => 'laranail-auth.'], notice: DeprecationNotice::None);

    expect(route('demo.page'))->toBe(route('laranail-demo.page'))
        ->and(route('legacy-auth.login'))->toBe(route('laranail-auth.login'));
});

it('treats a non-string answer from the previous resolver as no answer', function (mixed $answer): void {
    app('url')->resolveMissingNamedRoutesUsing(fn (string $name): mixed => $name === 'someone-else.odd' ? $answer : null);

    installDemoAliases();

    // Unchecked, a foreign object/int/array is a TypeError against ?string under strict_types.
    route('someone-else.odd');
})->with([
    'int'    => [42],
    'object' => [new stdClass],
    'array'  => [['http://localhost/elsewhere']],
])->throws(RouteNotFoundException::class);

it('asks the public router, not the generator internals, whether the scoped route exists', function (): void {
    $url = app('url');
    $real = app('router')->getRoutes();

    // The generator sees an EMPTY collection while the router holds the real one. A fallback reading
    // UrlGenerator::$routes would find nothing and leave the name missing; one asking the Router
    // finds the scoped route and delegates, which then fails on the generator NAMING THE SCOPED ROUTE.
    $url->setRoutes(new RouteCollection);
    installDemoAliases(DeprecationNotice::None);

    try {
        expect(fn (): string => route('demo.page'))
            ->toThrow(RouteNotFoundException::class, 'laranail-demo.page');
    } finally {
        $url->setRoutes($real);
    }
});

it('answers has() for scoped, bare and unknown names', function (): void {
    $aliases = installDemoAliases();

    $messages = collectDeprecations(function () use ($aliases): void {
        expect($aliases->has('laranail-demo.page'))->toBeTrue()
            ->and($aliases->has('demo.page'))->toBeTrue()
            ->and($aliases->has('demo-legacy-page'))->toBeTrue()
            ->and($aliases->has('demo.own'))->toBeTrue()
            ->and($aliases->has('demo.nothing-here'))->toBeFalse()
            ->and(Route::has('demo.page'))->toBeFalse(); // the gap has() exists to close
    });

    expect($messages)->toHaveCount(2);
});

it('answers currentIs() against the scoped name a deprecated pattern stands for', function (): void {
    $aliases = installDemoAliases();

    // Dispatch a real request so the router's current route is the scoped one.
    $this->get('/demo/item/x')->assertOk();
    expect(app('router')->currentRouteName())->toBe('laranail-demo.item.show');

    $messages = collectDeprecations(function () use ($aliases): void {
        expect($aliases->currentIs('laranail-demo.item.show'))->toBeTrue()
            ->and($aliases->currentIs('demo.item.show'))->toBeTrue()
            ->and($aliases->currentIs('demo.item.*'))->toBeTrue()
            ->and($aliases->currentIs('demo.page', 'laranail-demo.page'))->toBeFalse()
            ->and($aliases->currentIs('demo-legacy-page'))->toBeFalse();
    });

    expect($messages)->toHaveCount(2);
});

it('reports the scoped name a bare one stands for, without warning', function (): void {
    $aliases = installDemoAliases();

    $messages = collectDeprecations(function () use ($aliases): void {
        expect($aliases->scopedFor('demo.page'))->toBe('laranail-demo.page')
            ->and($aliases->scopedFor('demo-legacy-page'))->toBe('laranail-demo.page')
            ->and($aliases->scopedFor('demo.nothing-here'))->toBeNull();
    });

    expect($messages)->toBe([]);
});

/*
 * Contract: the one piece of framework internals this relies on.
 *
 * Laravel exposes a setter for the missing-route resolver and no getter, so chaining reads the
 * protected property. A rename upstream must fail HERE, in this package's CI, rather than at a
 * user's boot -- where all five former copies would have crashed together.
 */
it('pins UrlGenerator::$missingNamedRouteResolver against the installed framework', function (): void {
    $property = new ReflectionProperty(UrlGenerator::class, BareRouteNameAliases::RESOLVER_PROPERTY);

    expect($property->isProtected())->toBeTrue();

    $resolver = static fn (string $name): ?string => null;
    app('url')->resolveMissingNamedRoutesUsing($resolver);

    expect(BareRouteNameAliases::previousResolver(app('url')))->toBe($resolver);
});

it('fails loudly, not silently, when the property it reads does not exist', function (): void {
    $read = new ReflectionMethod(BareRouteNameAliases::class, 'readGeneratorProperty');

    $read->invoke(null, app('url'), 'noSuchResolverProperty');
})->throws(LogicException::class, 'noSuchResolverProperty');

it('reads a generator subclass the same way', function (): void {
    $generator = new class(app('router')->getRoutes(), Request::create('/')) extends UrlGenerator {};
    $resolver = static fn (): ?string => null;
    $generator->resolveMissingNamedRoutesUsing($resolver);

    expect(BareRouteNameAliases::previousResolver($generator))->toBe($resolver);
});

it('is bound to the Router type, not a facade', function (): void {
    // Guard against a refactor back to Route::has() inside the resolver, which a host swapping the
    // router binding would silently bypass.
    $constructor = new ReflectionMethod(BareRouteNameAliases::class, 'install');

    expect((string) $constructor->getParameters()[0]->getType())->toBe(Router::class);
});

<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Simtabi\Laranail\Package\Tools\Testing\FacadeCalls;
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoFacadeCalls;

uses(AssertsNoFacadeCalls::class);

/**
 * @return list<string> "rule call" for each finding
 */
function facadeFindings(string $source): array
{
    return array_map(static fn (array $f): string => "{$f[1]} {$f[2]}", FacadeCalls::in('<?php ' . $source));
}

/**
 * A directory holding the given files (relative path => source), removed after the test.
 *
 * @param array<string, string> $files
 */
function facadeFixtureDir(array $files): string
{
    $dir = sys_get_temp_dir() . '/no-facade-calls-' . bin2hex(random_bytes(6));
    foreach ($files as $path => $source) {
        @mkdir(dirname("{$dir}/{$path}"), 0777, true);
        file_put_contents("{$dir}/{$path}", '<?php ' . $source);
    }

    return $dir;
}

it('reports a facade imported by name, by alias, and written fully qualified', function (): void {
    expect(facadeFindings('namespace A; use Illuminate\Support\Facades\Cache; final class X { function f() { return Cache::get("k"); } }'))
        ->toBe(['facade Cache::'])
        ->and(facadeFindings('namespace A; use Illuminate\Support\Facades\Config as Cfg; final class X { function f() { return Cfg::get("k"); } }'))
        ->toBe(['facade Cfg::'])
        ->and(facadeFindings('namespace A; final class X { function f() { return \Illuminate\Support\Facades\DB::table("t"); } }'))
        ->toBe(['facade Illuminate\Support\Facades\DB::'])
        ->and(facadeFindings('namespace A; final class X { function f() { return \Cache::get("k"); } }'))
        ->toBe(['facade Cache::']);
});

it('reports the service-locating helpers', function (): void {
    expect(facadeFindings('namespace A; final class X { function f() { return [app("x"), config("y"), cache()->get("z"), \resolve("w")]; } }'))
        ->toBe(['helper app()', 'helper config()', 'helper cache()', 'helper resolve()']);
});

it('leaves alone what is not a facade or helper call', function (): void {
    expect(facadeFindings(<<<'PHP'
        namespace A;
        use Illuminate\Contracts\Cache\Repository;
        use Illuminate\Support\Facades\Cache;
        final class X {
            public function __construct(private Repository $cache) {}
            public function config(): array { return []; }        // a method named like a helper
            public function f() {
                $this->config();                                     // a method call
                self::g(); static::g(); Cache::class;                 // not facade calls
                now(); view('v'); __('k');                            // seams and rendering helpers
                \Some\Ns\app();                                       // a namespaced function
                return $this->cache->get('k');
            }
            private static function g(): void {}
        }
        PHP))->toBe([]);
});

it('skips a facade class itself, and honours the exemption marker', function (): void {
    expect(facadeFindings('namespace A; use Illuminate\Support\Facades\Facade; final class Thing extends Facade { protected static function getFacadeAccessor() { return app("x"); } }'))
        ->toBe([])
        ->and(facadeFindings("namespace A; final class X { function f() {\n// @facade-call-exempt: runs before the container exists\nreturn app('x'); } }"))
        ->toBe([]);
});

it('passes a clean tree, skips Providers, and counts what it scanned', function (): void {
    $dir = facadeFixtureDir([
        'Clean.php'                      => 'namespace A; final class Clean { function f(\Illuminate\Contracts\Config\Repository $c) { return $c->get("x"); } }',
        'Providers/AServiceProvider.php' => 'namespace A\Providers; final class AServiceProvider { function boot() { config("x"); } }',
    ]);

    $this->assertNoFacadeCallsIn($dir, minFiles: 1);

    [$scanned] = FacadeCalls::scan([$dir]);
    expect($scanned)->toBe(1);
});

it('fails on a facade call, naming the file and line', function (): void {
    $dir = facadeFixtureDir(['Leaky.php' => "namespace A;\nuse Illuminate\\Support\\Facades\\Cache;\nfinal class Leaky { function f() { return Cache::get('k'); } }"]);

    expect(fn () => $this->assertNoFacadeCallsIn($dir))
        ->toThrow(AssertionFailedError::class, 'Leaky.php:3 facade Cache::');
});

it('fails below the file floor rather than reporting a clean package', function (): void {
    $dir = facadeFixtureDir(['Only.php' => 'namespace A; final class Only {}']);

    expect(fn () => $this->assertNoFacadeCallsIn($dir, minFiles: 2))
        ->toThrow(AssertionFailedError::class, 'fewer than the floor of 2');
});

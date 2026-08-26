<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Path\Path;
use Simtabi\Laranail\Package\Tools\Support\Path\PathDirection;
use Simtabi\Laranail\Package\Tools\Support\Path\PathResolver;

require_once __DIR__ . '/../../fixtures/pathresolver/RootedCaller.php';
require_once __DIR__ . '/../../fixtures/pathresolver/a/b/c/Caller.php';

/* -------------------------------------------------------------- resolution */

it('resolves from the calling file, not from its own', function (): void {
    // The whole point. pheg's CoreTools::getRootPath() does dirname(__DIR__, $levels), where __DIR__
    // is CoreTools' own directory -- so every caller gets the same answer wherever it sits, and the
    // level count it passes means nothing.
    $fromFixture = PathResolverCallerFixture::climbTwo();

    expect($fromFixture)->toBe(dirname(PathResolverCallerFixture::ownDirectory(), 2))
        ->and($fromFixture)->not->toBe(dirname(__DIR__, 2));
});

it('appends a path after climbing', function (): void {
    expect(PathResolverCallerFixture::climbTwoInto('config/thing.php'))
        ->toBe(Path::join(dirname(PathResolverCallerFixture::ownDirectory(), 2), 'config/thing.php'));
});

it('normalises separators and a leading slash is rejected rather than trimmed', function (): void {
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: 'config//thing.php'))
        ->toBe(Path::join(dirname(__DIR__), 'config/thing.php'));
});

it('descends through real directory entries', function (): void {
    $root = Path::join(dirname(__DIR__, 2), 'fixtures/pathresolver');

    expect(PathResolverCallerFixture::descendFrom($root))->toBe(realpath(Path::join($root, 'a/b')));
});

/* ------------------------------------------------------------- arguments */

it('rejects a level count below one', function (): void {
    expect(PathResolver::resolve(levels: 0, direction: PathDirection::Outer))->toBeString();
})->throws(InvalidArgumentException::class, 'at least 1');

it('requires a path when descending', function (): void {
    expect(PathResolver::resolve(levels: 2, direction: PathDirection::Inner))->toBeString();
})->throws(InvalidArgumentException::class, 'needs a path to descend into');

it('requires the descent depth to match the path it is given', function (): void {
    expect(PathResolver::resolve(levels: 3, direction: PathDirection::Inner, path: 'a/b'))->toBeString();
})->throws(InvalidArgumentException::class, 'exactly 3 segment(s)');

it('refuses to climb past its root', function (): void {
    // dirname() saturates at "/" instead of failing, so without the depth check a too-large count
    // returns a plausible absolute path built on the root.
    expect(PathResolver::resolve(levels: 99, direction: PathDirection::Outer))->toBeString();
})->throws(RuntimeException::class, 'runs past its root');

/* -------------------------------------------------------------- security */

it('rejects a stream wrapper', function (string $path): void {
    // A phar:// path reaching a later require is remote code execution, not a wrong directory.
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: $path))->toBeString();
})->with([
    'phar://evil.phar/config.php',
    'file:///etc/passwd',
    'http://example.test/x.php',
    'data://text/plain;base64,PD9waHA=',
    'zip://payload.zip#c.php',
])->throws(InvalidArgumentException::class, 'stream wrapper');

it('rejects a null byte', function (): void {
    // PHP's path functions are C strings underneath and truncate at the byte, so "x.php\0.txt"
    // passes an extension check and then opens x.php.
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: "config.php\0.txt"))->toBeString();
})->throws(InvalidArgumentException::class, 'null byte');

it('rejects an absolute path', function (string $path): void {
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: $path))->toBeString();
})->with(['/etc/passwd', '\\windows\\system32'])->throws(InvalidArgumentException::class);

it('rejects a UNC network path', function (): void {
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: '\\\\attacker\\share\\x.php'))->toBeString();
})->throws(InvalidArgumentException::class, 'UNC');

it('rejects a Windows drive letter', function (): void {
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: 'C:\\windows\\system32'))->toBeString();
})->throws(InvalidArgumentException::class, 'drive letter');

it('rejects a traversal segment', function (): void {
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: 'config/../../../etc/passwd'))->toBeString();
})->throws(InvalidArgumentException::class, '".." segment');

it('allows a filename that merely contains dots', function (): void {
    // Traversal is checked per segment, so "cache..old" is not mistaken for it.
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: 'cache..old'))
        ->toBe(Path::join(dirname(__DIR__), 'cache..old'));
});

it('refuses a descent that leaves the tree through a symlink', function (): void {
    // The case plain string concatenation cannot catch: the segment is a genuine entry of its parent
    // and still lands outside. Only comparing the canonical result against the boundary sees it.
    $files = new Filesystem;
    $root = Path::join(dirname(__DIR__, 2), 'fixtures/pathresolver');
    $outside = Path::join(sys_get_temp_dir(), 'pathresolver-escape-' . getmypid());
    $link = Path::join($root, 'escape');

    $files->ensureDirectoryExists($outside);
    @symlink($outside, $link);

    if (! is_link($link)) {
        $files->deleteDirectory($outside);

        test()->markTestSkipped('the filesystem does not permit symlinks here');
    }

    try {
        expect(fn (): string => PathResolverCallerFixture::descendInto($root, 'escape'))
            ->toThrow(RuntimeException::class, 'escapes');
    } finally {
        @unlink($link);
        $files->deleteDirectory($outside);
    }
});

it('reports a segment that does not exist rather than returning the path', function (): void {
    $root = Path::join(dirname(__DIR__, 2), 'fixtures/pathresolver');

    expect(fn (): string => PathResolverCallerFixture::descendInto($root, 'nope'))
        ->toThrow(RuntimeException::class, 'has no entry named "nope"');
});

/* ------------------------------------------------------------- constants */

it('exposes the directions as constants on the resolver', function (): void {
    // The call-site spelling: PathResolver::OUTER, with no second import. These are the enum cases
    // themselves rather than copies, so the argument stays type-checked.
    expect(PathResolver::OUTER)->toBe(PathDirection::Outer)
        ->and(PathResolver::INNER)->toBe(PathDirection::Inner);
});

it('defaults the direction to OUTER', function (): void {
    expect(PathResolver::resolve(levels: 1))->toBe(dirname(__DIR__));
});

it('accepts the constant in place of the enum case', function (): void {
    expect(PathResolver::resolve(levels: 1, direction: PathResolver::OUTER, path: 'x.php'))
        ->toBe(Path::join(dirname(__DIR__), 'x.php'));
});

/* ----------------------------------------------------------- packageRoot */

it('finds the package root by marker rather than by counting', function (): void {
    // The level count is itself the fragile part -- a marker survives the file moving deeper, which
    // is the failure the counted form only guards against.
    expect(PathResolver::packageRoot())->toBe(dirname(__DIR__, 3));
});

it('finds the same root from a file at a different depth', function (): void {
    // Same answer from three directories further down, with no argument changed.
    expect(PathResolverCallerFixture::packageRoot())->toBe(dirname(__DIR__, 3));
});

it('rejects a marker that is not a single segment', function (): void {
    expect(PathResolver::packageRoot('config/app.php'))->toBeString();
})->throws(InvalidArgumentException::class, 'single path segment');

it('rejects an unsafe marker', function (): void {
    expect(PathResolver::packageRoot('phar://x'))->toBeString();
})->throws(InvalidArgumentException::class, 'stream wrapper');

it('reports when no ancestor carries the marker', function (): void {
    expect(PathResolver::packageRoot('this-marker-does-not-exist'))->toBeString();
})->throws(RuntimeException::class, 'No ancestor of the calling file');

/* ------------------------------------------------- provider packagePath() */

it('resolves a package path from the provider wherever the provider sits', function (): void {
    // getPackageBaseDir() reflects on the provider class and steps out of Providers/ and src/, so
    // this counts nothing and survives the provider moving.
    $provider = new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void {}

        public function exposePath(string $relative = ''): string
        {
            return $this->packagePath($relative);
        }
    };

    // The anonymous class is declared in this test file, so its "package root" is two levels above
    // tests/Feature/Support -- the tests directory's parent chain, not package-tools' own root.
    expect($provider->exposePath())->toBeString()
        ->and($provider->exposePath('config/x.php'))
        ->toBe(Path::join($provider->exposePath(), 'config/x.php'));
});

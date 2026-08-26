<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
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
    (void) PathResolver::resolve(levels: 0, direction: PathDirection::Outer);
})->throws(InvalidArgumentException::class, 'at least 1');

it('requires a path when descending', function (): void {
    (void) PathResolver::resolve(levels: 2, direction: PathDirection::Inner);
})->throws(InvalidArgumentException::class, 'needs a path to descend into');

it('requires the descent depth to match the path it is given', function (): void {
    (void) PathResolver::resolve(levels: 3, direction: PathDirection::Inner, path: 'a/b');
})->throws(InvalidArgumentException::class, 'exactly 3 segment(s)');

it('refuses to climb past the filesystem root', function (): void {
    // dirname() saturates at "/" instead of failing, so without the depth check a too-large count
    // returns a plausible absolute path built on the root.
    (void) PathResolver::resolve(levels: 99, direction: PathDirection::Outer);
})->throws(RuntimeException::class, 'past the filesystem root');

/* -------------------------------------------------------------- security */

it('rejects a stream wrapper', function (string $path): void {
    // A phar:// path reaching a later require is remote code execution, not a wrong directory.
    (void) PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: $path);
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
    (void) PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: "config.php\0.txt");
})->throws(InvalidArgumentException::class, 'null byte');

it('rejects an absolute path', function (string $path): void {
    (void) PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: $path);
})->with(['/etc/passwd', '\\windows\\system32'])->throws(InvalidArgumentException::class);

it('rejects a UNC network path', function (): void {
    (void) PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: '\\\\attacker\\share\\x.php');
})->throws(InvalidArgumentException::class, 'UNC');

it('rejects a Windows drive letter', function (): void {
    (void) PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: 'C:\\windows\\system32');
})->throws(InvalidArgumentException::class, 'drive letter');

it('rejects a traversal segment', function (): void {
    (void) PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: 'config/../../../etc/passwd');
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
        expect(fn () => PathResolverCallerFixture::descendInto($root, 'escape'))
            ->toThrow(RuntimeException::class, 'escapes');
    } finally {
        @unlink($link);
        $files->deleteDirectory($outside);
    }
});

it('reports a segment that does not exist rather than returning the path', function (): void {
    $root = Path::join(dirname(__DIR__, 2), 'fixtures/pathresolver');

    expect(fn () => PathResolverCallerFixture::descendInto($root, 'nope'))
        ->toThrow(RuntimeException::class, 'has no entry named "nope"');
});

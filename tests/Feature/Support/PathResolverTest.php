<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Support\Path\PathDirection;
use Simtabi\Laranail\Package\Tools\Support\Path\PathResolver;

require_once __DIR__ . '/../../fixtures/pathresolver/a/b/c/Caller.php';

it('resolves from the calling file, not from its own', function (): void {
    // The whole point. pheg's CoreTools::getRootPath() does dirname(__DIR__, $levels), where __DIR__
    // is CoreTools' own directory -- so every caller gets the same answer regardless of where it sits.
    $fromFixture = PathResolverCallerFixture::climbTwo();

    expect($fromFixture)->toBe(dirname(PathResolverCallerFixture::ownDirectory(), 2))
        ->and($fromFixture)->not->toBe(dirname(__DIR__, 2));
});

it('appends a path after climbing', function (): void {
    expect(PathResolverCallerFixture::climbTwoInto('config/thing.php'))
        ->toBe(dirname(PathResolverCallerFixture::ownDirectory(), 2) . '/config/thing.php');
});

it('rejects a level count below one', function (): void {
    PathResolver::resolve(levels: 0, direction: PathDirection::Outer);
})->throws(InvalidArgumentException::class, 'at least 1');

it('rejects dot-dot inside the path', function (): void {
    // Allowing both spellings at once is how a path becomes unreadable and a move becomes unsafe.
    PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: '../config');
})->throws(InvalidArgumentException::class, '".."');

it('refuses to climb past the filesystem root', function (): void {
    // dirname() saturates at "/" instead of failing, so without this check a too-large level count
    // returns a plausible-looking absolute path built on the root.
    PathResolver::resolve(levels: 99, direction: PathDirection::Outer);
})->throws(RuntimeException::class, 'past the filesystem root');

it('requires a path when descending', function (): void {
    PathResolver::resolve(levels: 2, direction: PathDirection::Inner);
})->throws(InvalidArgumentException::class, 'needs a path to descend into');

it('requires the descent depth to match the path it is given', function (): void {
    PathResolver::resolve(levels: 3, direction: PathDirection::Inner, path: 'config/thing.php');
})->throws(InvalidArgumentException::class, 'exactly 3 segment(s)');

it('descends into the calling directory', function (): void {
    // The caller here is this file, so the base is tests/Feature/Support.
    expect(PathResolver::resolve(levels: 2, direction: PathDirection::Inner, path: 'one/two'))
        ->toBe(__DIR__ . '/one/two');
});

it('normalises separators and ignores a leading slash on the appended path', function (): void {
    expect(PathResolver::resolve(levels: 1, direction: PathDirection::Outer, path: '/config/thing.php'))
        ->toBe(dirname(__DIR__) . '/config/thing.php');
});

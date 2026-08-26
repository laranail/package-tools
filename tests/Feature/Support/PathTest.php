<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Support\Path\Path;

it('joins with the platform separator whichever separator it is given', function (): void {
    expect(Path::join('a', 'b/c', 'd\\e'))
        ->toBe(implode(Path::SEPARATOR, ['a', 'b', 'c', 'd', 'e']));
});

it('keeps a leading separator so an absolute path stays absolute', function (): void {
    expect(Path::join('/var', 'www'))->toBe(Path::SEPARATOR . 'var' . Path::SEPARATOR . 'www');
});

it('drops empty parts instead of doubling the separator', function (): void {
    // So an optional trailing segment can be passed straight through without a conditional.
    expect(Path::join('a', '', 'b'))->toBe('a' . Path::SEPARATOR . 'b')
        ->and(Path::join('a//b'))->toBe('a' . Path::SEPARATOR . 'b');
});

it('normalises a mixed-separator path', function (): void {
    expect(Path::normalise('a\\b/c'))->toBe(implode(Path::SEPARATOR, ['a', 'b', 'c']));
});

it('reports depth in segments', function (): void {
    expect(Path::depth('/a/b/c'))->toBe(3)
        ->and(Path::depth('/'))->toBe(0);
});

it('treats a path as within itself', function (): void {
    expect(Path::isWithin('/a/b', '/a/b'))->toBeTrue();
});

it('treats a descendant as within', function (): void {
    expect(Path::isWithin('/a/b', '/a/b/c/d'))->toBeTrue();
});

it('does not treat a sibling with a shared prefix as within', function (): void {
    // The whole reason isWithin exists rather than str_starts_with. Getting this wrong in a
    // containment check means a security decision made on a string coincidence.
    expect(Path::isWithin('/a/b', '/a/bc'))->toBeFalse();
});

it('compares across separator styles', function (): void {
    expect(Path::isWithin('/a/b', '\\a\\b\\c'))->toBeTrue();
});

it('does not treat an ancestor as within', function (): void {
    expect(Path::isWithin('/a/b', '/a'))->toBeFalse();
});

it('splits a Windows-style absolute path into segments', function (): void {
    // The SBOM output-path check split on "/" alone, so a Windows path stayed one segment: ".." was
    // never seen, and the containment test compared two unsplit strings.
    expect(Path::segments('C:\\project\\..\\etc'))->toBe(['C:', 'project', '..', 'etc']);
});

/* ------------------------------------------------------- roots and network paths */

it('preserves a UNC prefix instead of collapsing it to a local root', function (): void {
    // The corruption this fixes: "\\server\share\pkg" became "/server/share/pkg", silently rewriting
    // a network path into a local absolute one.
    expect(Path::normalise('\\\\server\share\pkg'))
        ->toBe(str_repeat(Path::SEPARATOR, 2) . implode(Path::SEPARATOR, ['server', 'share', 'pkg']));
});

it('recognises a UNC path as a network path', function (): void {
    expect(Path::isNetworkPath('\\\\server\share\pkg'))->toBeTrue()
        ->and(Path::isNetworkPath('/server/share/pkg'))->toBeFalse()
        ->and(Path::isNetworkPath('relative/path'))->toBeFalse();
});

it('treats a UNC share as the floor, not as two segments', function (): void {
    // Otherwise a climb walks above the share into a path that cannot exist.
    expect(Path::depth('\\\\server\share'))->toBe(0)
        ->and(Path::depth('\\\\server\share\pkg\src'))->toBe(2);
});

it('does not place a local path inside a UNC share that spells the same', function (): void {
    expect(Path::isWithin('\\\\server\share', '/server/share/pkg'))->toBeFalse();
});

it('joins onto a UNC root without losing the prefix', function (): void {
    expect(Path::join('\\\\server\share', 'pkg'))
        ->toBe(str_repeat(Path::SEPARATOR, 2) . implode(Path::SEPARATOR, ['server', 'share', 'pkg']));
});

it('counts a drive-letter path below its drive', function (): void {
    // depth() said 2, so climbing 2 passed the guard and dirname() returned "." -- a relative path
    // that then resolves against the working directory.
    expect(Path::depth('C:\project'))->toBe(1)
        ->and(Path::split('C:\project')[0])->toBe('C:' . Path::SEPARATOR);
});

it('distinguishes a drive-relative path from a drive-absolute one', function (): void {
    expect(Path::split('C:rel')[0])->toBe('C:')
        ->and(Path::split('C:\abs')[0])->toBe('C:' . Path::SEPARATOR);
});

it('reports what is absolute', function (): void {
    expect(Path::isAbsolute('/a'))->toBeTrue()
        ->and(Path::isAbsolute('C:\a'))->toBeTrue()
        ->and(Path::isAbsolute('\\\\srv\share'))->toBeTrue()
        ->and(Path::isAbsolute('a/b'))->toBeFalse();
});

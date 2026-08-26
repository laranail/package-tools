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

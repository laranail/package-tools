<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Support\ErrorStorage;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Support\ErrorStorage\ErrorStorageService;

final class ErrorStorageServiceTest extends TestCase
{
    public function test_starts_empty(): void
    {
        $bag = ErrorStorageService::create();

        $this->assertFalse($bag->hasErrors());
        $this->assertSame(0, $bag->getErrorCount());
        $this->assertNull($bag->getFirstError());
    }

    public function test_set_errors_with_array(): void
    {
        $bag = ErrorStorageService::withErrors([
            'foo' => 'foo failed',
            'bar' => 'bar failed',
        ]);

        $this->assertTrue($bag->hasErrors());
        $this->assertSame(2, $bag->getErrorCount());
        $this->assertSame(['foo failed'], $bag->getErrors('foo'));
    }

    public function test_set_errors_with_string_wraps(): void
    {
        $bag = ErrorStorageService::withErrors('top-level error');

        $this->assertSame(1, $bag->getErrorCount());
        $this->assertSame('top-level error', $bag->getFirstError());
    }

    public function test_add_error_promotes_repeats_to_list(): void
    {
        $bag = ErrorStorageService::create()
            ->addError('field', 'first')
            ->addError('field', 'second');

        $this->assertSame(['first', 'second'], $bag->getErrors('field'));
        $this->assertSame('first', $bag->getFirstError());
    }

    public function test_clear_errors_resets_state(): void
    {
        $bag = ErrorStorageService::withErrors(['a' => 'b'])->clearErrors();

        $this->assertFalse($bag->hasErrors());
        $this->assertSame(0, $bag->getErrorCount());
    }

    public function test_get_errors_returns_empty_array_for_unknown_key(): void
    {
        $bag = ErrorStorageService::withErrors(['a' => 'b']);

        $this->assertSame([], $bag->getErrors('missing'));
    }

    public function test_set_errors_merges_when_called_twice(): void
    {
        $bag = ErrorStorageService::withErrors(['a' => 'one'])
            ->setErrors(['b' => 'two']);

        $this->assertSame(2, $bag->getErrorCount());
        $this->assertSame(['one'], $bag->getErrors('a'));
        $this->assertSame(['two'], $bag->getErrors('b'));
    }
}

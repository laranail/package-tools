<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Exceptions;

use Exception;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Exceptions\InvalidPackage;

/**
 * InvalidPackageTest - Tests for InvalidPackage exception
 */
class InvalidPackageTest extends TestCase
{
    public function test_it_creates_name_is_required_exception(): void
    {
        $exception = InvalidPackage::nameIsRequired();

        $this->assertInstanceOf(InvalidPackage::class, $exception);
        $this->assertStringContainsString('does not have a name', $exception->getMessage());
        $this->assertStringContainsString('$package->name', $exception->getMessage());
    }

    public function test_name_is_required_exception_has_helpful_message(): void
    {
        $exception = InvalidPackage::nameIsRequired();

        $message = $exception->getMessage();

        $this->assertStringContainsString('name', $message);
        $this->assertStringContainsString('set one', $message);
    }

    public function test_exception_extends_base_exception(): void
    {
        $exception = InvalidPackage::nameIsRequired();

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_exception_can_be_thrown_and_caught(): void
    {
        $this->expectException(InvalidPackage::class);

        throw InvalidPackage::nameIsRequired();
    }

    public function test_exception_message_is_string(): void
    {
        $exception = InvalidPackage::nameIsRequired();

        $this->assertIsString($exception->getMessage());
        $this->assertNotEmpty($exception->getMessage());
    }
}

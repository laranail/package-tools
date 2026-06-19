<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Component;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Services\Component\ComponentValidator;

final class InstantiableComponent
{
    public function render(): string
    {
        return '';
    }
}

abstract class AbstractComponent
{
    abstract public function render(): string;
}

final class ComponentValidatorTest extends TestCase
{
    private ComponentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ComponentValidator;
    }

    public function test_valid_instantiable_class_passes(): void
    {
        $this->assertSame([], $this->validator->validate(InstantiableComponent::class));
        $this->assertTrue($this->validator->isValid(InstantiableComponent::class));
    }

    public function test_empty_class_name_is_rejected(): void
    {
        $this->assertContains('Component class name cannot be empty', $this->validator->validate(''));
        $this->assertContains('Component class name cannot be empty', $this->validator->validate('0'));
    }

    public function test_nonexistent_class_is_rejected(): void
    {
        $errors = $this->validator->validate('Acme\\Does\\Not\\Exist');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('does not exist', $errors[0]);
    }

    public function test_abstract_class_is_not_instantiable(): void
    {
        $errors = $this->validator->validate(AbstractComponent::class);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('not instantiable', $errors[0]);
    }

    public function test_component_array_happy_path(): void
    {
        $this->assertSame([], $this->validator->validate([
            'alert' => InstantiableComponent::class,
        ]));
    }

    public function test_component_array_reports_non_string_class(): void
    {
        $errors = $this->validator->validate(['alert' => 123]);

        $this->assertContains('Component class must be a string for component: alert', $errors);
    }

    public function test_component_array_reports_invalid_class_with_name_context(): void
    {
        $errors = $this->validator->validate(['alert' => 'Nope\\Missing']);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString("Invalid component 'alert'", $errors[0]);
        $this->assertStringContainsString('does not exist', $errors[0]);
    }

    public function test_rejects_non_string_non_array_input(): void
    {
        $this->assertContains('Invalid input type. Expected string (class name) or array', $this->validator->validate(3.14));
    }
}

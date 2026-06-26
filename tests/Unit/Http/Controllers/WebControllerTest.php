<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use ReflectionClass;
use Simtabi\Laranail\Package\Tools\Http\Controllers\WebController;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class WebControllerTest extends TestCase
{
    public function test_extends_laravel_base_controller(): void
    {
        $rc = new ReflectionClass(WebController::class);

        self::assertSame(BaseController::class, $rc->getParentClass()->getName());
        self::assertTrue($rc->isAbstract(), 'WebController is intended as a base for package controllers.');
    }

    public function test_ships_the_same_trait_set_as_make_controller_default(): void
    {
        $traits = $this->collectTraits(WebController::class);

        // These three are the exact set Laravel's `make:controller` emits
        // into a fresh app controller. Regression catches a strip-down
        // that would break consumers relying on $this->validate(),
        // $this->authorize(), $this->dispatch().
        self::assertContains(AuthorizesRequests::class, $traits);
        self::assertContains(DispatchesJobs::class, $traits);
        self::assertContains(ValidatesRequests::class, $traits);
    }

    /**
     * @return list<class-string>
     */
    private function collectTraits(string $class): array
    {
        $collected = [];
        $current = $class;
        while ($current !== false) {
            $collected = [...$collected, ...class_uses($current)];
            $current = get_parent_class($current);
        }

        return array_values(array_unique($collected));
    }
}

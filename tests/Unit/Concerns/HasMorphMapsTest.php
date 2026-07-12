<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasMorphMaps;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * declarative morph-map registration: spec storage only — boot behavior
 * (config resolution + Relation::morphMap) is feature-level.
 */
final class HasMorphMapsTest extends TestCase
{
    use HasMorphMaps;

    #[Test]
    public function it_stores_a_static_map(): void
    {
        $this->registerMorphMap(['post' => 'Acme\\Blog\\Models\\Post']);

        $this->assertSame([['post' => 'Acme\\Blog\\Models\\Post']], $this->morphMaps);
    }

    #[Test]
    public function it_stores_a_lazy_closure_unevaluated(): void
    {
        $evaluated = false;
        $this->registerMorphMap(static function () use (&$evaluated): array {
            $evaluated = true;

            return [];
        });

        $this->assertCount(1, $this->morphMaps);
        $this->assertFalse($evaluated, 'closures must stay unevaluated until boot');
    }

    #[Test]
    public function maps_accumulate_in_registration_order(): void
    {
        $this->registerMorphMap(['post' => 'Acme\\Post']);
        $this->registerMorphMap(['comment' => 'Acme\\Comment']);

        $this->assertSame(
            [['post' => 'Acme\\Post'], ['comment' => 'Acme\\Comment']],
            $this->morphMaps,
        );
    }

    #[Test]
    public function it_stores_a_config_spec_with_defaults(): void
    {
        $this->registerMorphMapFromConfig('acme.morph_map');

        $this->assertSame([[
            'map' => 'acme.morph_map',
            'user_model' => null,
            'user_alias' => 'user',
        ]], $this->morphMapConfigSpecs);
    }

    #[Test]
    public function it_stores_a_fully_specified_config_spec(): void
    {
        $this->registerMorphMapFromConfig('acme.morph_map', 'acme.user_model', 'account');

        $this->assertSame([[
            'map' => 'acme.morph_map',
            'user_model' => 'acme.user_model',
            'user_alias' => 'account',
        ]], $this->morphMapConfigSpecs);
    }

    #[Test]
    public function a_null_user_alias_disables_the_user_entry(): void
    {
        $this->registerMorphMapFromConfig('acme.morph_map', null, null);

        $this->assertNull($this->morphMapConfigSpecs[0]['user_alias']);
    }

    #[Test]
    public function registration_is_fluent(): void
    {
        $result = $this->registerMorphMap(['post' => 'Acme\\Post'])
            ->registerMorphMapFromConfig('acme.morph_map');

        $this->assertSame($this, $result);
    }
}

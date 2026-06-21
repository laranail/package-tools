<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Database;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Database\SeederRegistry;

final class SeederRegistryTest extends TestCase
{
    public function test_register_stores_configuration(): void
    {
        $r = new SeederRegistry;
        $r->register('vendor/pkg', ['App\\OneSeeder'], 'Vendor\\Pkg', ['fire_events' => true]);

        $this->assertSame(1, $r->count());
        $config = $r->get('vendor/pkg');
        $this->assertSame(['App\\OneSeeder'], $config['seeders']);
        $this->assertSame('Vendor\\Pkg', $config['namespace']);
        $this->assertTrue($config['options']['fire_events']);
    }

    public function test_register_replaces_existing_entry_for_same_key(): void
    {
        $r = new SeederRegistry;
        $r->register('k', ['A']);
        $r->register('k', ['B', 'C']);

        $this->assertSame(['B', 'C'], $r->get('k')['seeders']);
        $this->assertSame(1, $r->count());
    }

    public function test_forget_removes_entry(): void
    {
        $r = new SeederRegistry;
        $r->register('a', ['X']);
        $r->register('b', ['Y']);
        $r->forget('a');

        $this->assertNull($r->get('a'));
        $this->assertNotNull($r->get('b'));
    }

    public function test_clear_drops_everything(): void
    {
        $r = new SeederRegistry;
        $r->register('a', ['X']);
        $r->register('b', ['Y']);
        $r->clear();

        $this->assertTrue($r->isEmpty());
        $this->assertSame(0, $r->count());
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull((new SeederRegistry)->get('does-not-exist'));
    }
}

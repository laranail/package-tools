<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Config;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigMerger;

final class ConfigMergerTest extends TestCase
{
    private ConfigMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->merger = new ConfigMerger;
    }

    public function test_deep_merge_recursively_combines_nested_arrays(): void
    {
        $base = ['db' => ['host' => 'localhost', 'port' => 3306]];
        $merge = ['db' => ['port' => 5432, 'user' => 'admin']];

        $this->assertSame(
            ['db' => ['host' => 'localhost', 'port' => 5432, 'user' => 'admin']],
            $this->merger->deepMerge($base, $merge),
        );
    }

    public function test_deep_merge_overrides_scalar_with_later_value(): void
    {
        $this->assertSame(
            ['name' => 'new'],
            $this->merger->deepMerge(['name' => 'old'], ['name' => 'new']),
        );
    }

    public function test_deep_merge_replaces_scalar_with_array(): void
    {
        $this->assertSame(
            ['key' => ['a' => 1]],
            $this->merger->deepMerge(['key' => 'scalar'], ['key' => ['a' => 1]]),
        );
    }

    public function test_deep_merge_replaces_array_with_scalar(): void
    {
        $this->assertSame(
            ['key' => 'scalar'],
            $this->merger->deepMerge(['key' => ['a' => 1]], ['key' => 'scalar']),
        );
    }

    public function test_deep_merge_with_empty_merge_returns_base(): void
    {
        $base = ['a' => 1, 'b' => ['c' => 2]];

        $this->assertSame($base, $this->merger->deepMerge($base, []));
    }

    public function test_deep_merge_with_empty_base_returns_merge(): void
    {
        $merge = ['a' => 1, 'b' => ['c' => 2]];

        $this->assertSame($merge, $this->merger->deepMerge([], $merge));
    }

    public function test_deep_merge_does_not_recurse_into_list_indexes(): void
    {
        // Numerically-indexed arrays are treated like any other array key:
        // matching keys recurse, so element 0 wins from $merge.
        $this->assertSame(
            ['one', 'two'],
            $this->merger->deepMerge(['zero', 'two'], ['one']),
        );
    }

    public function test_replace_strategy_ignores_base_entirely(): void
    {
        $this->assertSame(
            ['only' => 'merge'],
            $this->merger->replaceStrategy(['only' => 'base', 'extra' => 1], ['only' => 'merge']),
        );
    }

    public function test_append_strategy_concatenates_arrays(): void
    {
        $this->assertSame(
            ['list' => ['a', 'b', 'c']],
            $this->merger->appendStrategy(['list' => ['a']], ['list' => ['b', 'c']]),
        );
    }

    public function test_append_strategy_replaces_scalar_values(): void
    {
        $this->assertSame(
            ['count' => 5],
            $this->merger->appendStrategy(['count' => 1], ['count' => 5]),
        );
    }

    public function test_append_strategy_replaces_when_types_differ(): void
    {
        $this->assertSame(
            ['x' => ['a']],
            $this->merger->appendStrategy(['x' => 'scalar'], ['x' => ['a']]),
        );
    }

    public function test_append_strategy_adds_missing_keys(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2],
            $this->merger->appendStrategy(['a' => 1], ['b' => 2]),
        );
    }

    public function test_merge_with_strategy_defaults_to_deep(): void
    {
        $result = $this->merger->mergeWithStrategy(
            ['db' => ['host' => 'localhost']],
            ['db' => ['port' => 3306]],
        );

        $this->assertSame(['db' => ['host' => 'localhost', 'port' => 3306]], $result);
    }

    public function test_merge_with_strategy_dispatches_replace(): void
    {
        $this->assertSame(
            ['b' => 2],
            $this->merger->mergeWithStrategy(['a' => 1], ['b' => 2], 'replace'),
        );
    }

    public function test_merge_with_strategy_dispatches_append(): void
    {
        $this->assertSame(
            ['l' => ['a', 'b']],
            $this->merger->mergeWithStrategy(['l' => ['a']], ['l' => ['b']], 'append'),
        );
    }

    public function test_merge_with_strategy_unknown_falls_back_to_deep(): void
    {
        $result = $this->merger->mergeWithStrategy(
            ['x' => ['a' => 1]],
            ['x' => ['b' => 2]],
            'nonsense',
        );

        $this->assertSame(['x' => ['a' => 1, 'b' => 2]], $result);
    }
}

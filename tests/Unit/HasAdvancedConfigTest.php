<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasAdvancedConfig;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasAdvancedConfigTest - Test advanced configuration management
 */
class HasAdvancedConfigTest extends TestCase
{
    use HasAdvancedConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing test config
        Config::set('test', []);
        Config::set('services', []);
    }

    #[Test]
    public function it_queues_config_merges(): void
    {
        $this->mergeConfigInto('services', 'services');

        $queued = $this->getQueuedConfigMerges();

        $this->assertCount(1, $queued);
        $this->assertEquals('services', $queued['services']['source']);
        $this->assertEquals('services', $queued['services']['target']);
    }

    #[Test]
    public function it_performs_deep_merge(): void
    {
        $target = [
            'key1' => 'value1',
            'nested' => [
                'a' => 1,
                'b' => 2,
            ],
        ];

        $source = [
            'key2' => 'value2',
            'nested' => [
                'b' => 3,
                'c' => 4,
            ],
        ];

        $result = $this->deepMerge($target, $source);

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'nested' => [
                'a' => 1,
                'b' => 3,
                'c' => 4,
            ],
        ], $result);
    }

    #[Test]
    public function it_replaces_scalar_values_in_deep_merge(): void
    {
        $target = ['key' => 'old'];
        $source = ['key' => 'new'];

        $result = $this->deepMerge($target, $source);

        $this->assertEquals(['key' => 'new'], $result);
    }

    #[Test]
    public function it_handles_null_values_in_merge(): void
    {
        $target = ['key' => 'value'];
        $source = ['key' => null];

        $result = $this->deepMerge($target, $source);

        $this->assertNull($result['key']);
    }

    #[Test]
    public function it_finds_conflicts_between_arrays(): void
    {
        $target = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $source = [
            'key2' => 'new_value',
            'key3' => 'value3',
        ];

        $conflicts = $this->findConflicts($target, $source);

        $this->assertCount(1, $conflicts);
        $this->assertContains('key2', $conflicts);
    }

    #[Test]
    public function it_finds_nested_conflicts(): void
    {
        $target = [
            'nested' => [
                'a' => 1,
                'b' => 2,
            ],
        ];

        $source = [
            'nested' => [
                'b' => 3,
                'c' => 4,
            ],
        ];

        $conflicts = $this->findConflicts($target, $source);

        $this->assertCount(1, $conflicts);
        $this->assertContains('nested.b', $conflicts);
    }

    #[Test]
    public function it_disables_safe_mode(): void
    {
        $this->assertTrue($this->isConfigSafeMode());

        $this->disableConfigSafeMode();

        $this->assertFalse($this->isConfigSafeMode());
    }

    #[Test]
    public function it_enables_safe_mode(): void
    {
        $this->disableConfigSafeMode();
        $this->assertFalse($this->isConfigSafeMode());

        $this->enableConfigSafeMode();

        $this->assertTrue($this->isConfigSafeMode());
    }

    #[Test]
    public function it_clears_queued_merges(): void
    {
        $this->mergeConfigInto('services', 'services');
        $this->mergeConfigInto('auth', 'auth');

        $this->assertCount(2, $this->getQueuedConfigMerges());

        $this->clearConfigMerges();

        $this->assertCount(0, $this->getQueuedConfigMerges());
    }

    #[Test]
    public function it_supports_deep_merge_flag(): void
    {
        $this->mergeConfigInto('services', 'services', deep: true);
        $this->mergeConfigInto('auth', 'auth', deep: false);

        $queued = $this->getQueuedConfigMerges();

        $this->assertTrue($queued['services']['deep']);
        $this->assertFalse($queued['auth']['deep']);
    }

    // Abstract method implementation for testing
    protected function getConfigNamespace(): string
    {
        return 'testpackage';
    }
}

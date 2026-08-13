<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Config;

use Override;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Contracts\ConfigManagerInterface;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigManager;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

final class ConfigManagerTest extends TestCase
{
    private ConfigManager $config;

    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new ConfigManager($this->app->make('config'), $this->app);

        $this->sandbox = sys_get_temp_dir() . '/laranail-config-' . bin2hex(random_bytes(6));
        mkdir($this->sandbox . '/config/packages', 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->sandbox)) {
            exec('rm -rf ' . escapeshellarg($this->sandbox));
        }

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class];
    }

    // -----------------------------------------------------------------
    // remove() — the behaviour that was broken
    // -----------------------------------------------------------------

    #[Test]
    public function removing_a_top_level_key_makes_both_get_and_has_miss(): void
    {
        config()->set('doomed', ['a' => 1]);

        self::assertTrue(config()->has('doomed'));

        $this->config->remove('doomed');

        self::assertNull(config()->get('doomed'), 'get() still returns the removed value.');
        self::assertFalse(config()->has('doomed'), 'has() still reports a removed top-level key.');
    }

    #[Test]
    public function removing_a_top_level_key_leaves_its_siblings_alone(): void
    {
        config()->set('doomed', 'x');
        config()->set('kept', 'y');
        config()->set('nested', ['deep' => ['value' => 1]]);

        $this->config->remove('doomed');

        self::assertSame('y', config()->get('kept'));
        self::assertSame(1, config()->get('nested.deep.value'));
    }

    #[Test]
    public function removing_a_nested_key_leaves_its_parent_intact(): void
    {
        config()->set('svc', ['keep' => 1, 'drop' => 2]);

        $this->config->remove('svc.drop');

        self::assertSame(['keep' => 1], config()->get('svc'));
        self::assertFalse(config()->has('svc.drop'));
    }

    #[Test]
    public function forget_is_an_alias_of_remove(): void
    {
        config()->set('gone', 'x');

        $this->config->forget('gone');

        self::assertFalse(config()->has('gone'));
    }

    // -----------------------------------------------------------------
    // Core surface
    // -----------------------------------------------------------------

    #[Test]
    public function it_sets_gets_and_overrides(): void
    {
        $this->config->set('a.b', 1)->override('a.b', 2);

        self::assertSame(2, $this->config->get('a.b'));
        self::assertTrue($this->config->has('a.b'));
        self::assertSame('fallback', $this->config->get('nope', 'fallback'));
    }

    #[Test]
    public function set_if_missing_does_not_overwrite(): void
    {
        $this->config->set('x', 'first')->setIfMissing('x', 'second');

        self::assertSame('first', $this->config->get('x'));
    }

    #[Test]
    public function merge_is_a_true_deep_merge(): void
    {
        config()->set('svc', ['mail' => ['host' => 'a', 'port' => 25], 'keep' => true]);

        $this->config->merge('svc', ['mail' => ['host' => 'b']]);

        // array_merge_recursive would produce ['a', 'b'] for host.
        self::assertSame('b', $this->config->get('svc.mail.host'));
        self::assertSame(25, $this->config->get('svc.mail.port'));
        self::assertTrue($this->config->get('svc.keep'));
    }

    #[Test]
    public function it_pushes_and_prepends(): void
    {
        config()->set('list', ['b']);

        $this->config->push('list', 'c')->prepend('list', 'a');

        self::assertSame(['a', 'b', 'c'], $this->config->get('list'));
    }

    #[Test]
    public function it_transforms_and_maps(): void
    {
        config()->set('n', 2);
        config()->set('items', ['a' => 1, 'b' => 2]);

        $this->config
            ->transform('n', fn (int $v): int => $v * 10)
            ->each('items', fn (int $v): int => $v + 1);

        self::assertSame(20, $this->config->get('n'));
        self::assertSame(['a' => 2, 'b' => 3], $this->config->get('items'));
    }

    #[Test]
    public function conditionals_run_only_when_they_should(): void
    {
        $this->config
            ->when(true, fn (ConfigManagerInterface $c): ConfigManagerInterface => $c->set('yes', 1))
            ->when(false, fn (ConfigManagerInterface $c): ConfigManagerInterface => $c->set('no', 1))
            ->unless(false, fn (ConfigManagerInterface $c): ConfigManagerInterface => $c->set('unless', 1))
            ->inEnvironment('testing', fn (ConfigManagerInterface $c): ConfigManagerInterface => $c->set('env', 1))
            ->inEnvironment('production', fn (ConfigManagerInterface $c): ConfigManagerInterface => $c->set('prod', 1));

        self::assertSame(1, $this->config->get('yes'));
        self::assertNull($this->config->get('no'));
        self::assertSame(1, $this->config->get('unless'));
        self::assertSame(1, $this->config->get('env'));
        self::assertNull($this->config->get('prod'));
    }

    #[Test]
    public function when_has_receives_the_current_value(): void
    {
        config()->set('present', 'v');
        $seen = null;

        $this->config->whenHas('present', function ($c, $value) use (&$seen): void {
            $seen = $value;
        });

        self::assertSame('v', $seen);
    }

    #[Test]
    public function override_section_copies_one_section(): void
    {
        $source = ['db' => ['host' => 'localhost', 'port' => 3306], 'other' => ['x' => 1]];

        $this->config->overrideSection($source, 'db', 'database.connections.custom');

        self::assertSame('localhost', $this->config->get('database.connections.custom.host'));
        self::assertNull($this->config->get('database.connections.custom.x'));
    }

    // -----------------------------------------------------------------
    // Logging
    // -----------------------------------------------------------------

    #[Test]
    public function nothing_is_logged_until_logging_is_enabled(): void
    {
        $this->config->set('a', 1);
        self::assertSame([], $this->config->getLog());

        $this->config->withLogging()->set('b', 2);
        self::assertSame([['operation' => 'set', 'key' => 'b', 'value' => 2]], $this->config->getLog());

        $this->config->clearLog();
        self::assertSame([], $this->config->getLog());
    }

    #[Test]
    public function a_genuine_null_value_is_logged(): void
    {
        $this->config->withLogging()->set('n', null);

        self::assertSame([['operation' => 'set', 'key' => 'n', 'value' => null]], $this->config->getLog());
    }

    #[Test]
    public function an_operation_without_a_value_omits_the_value_key(): void
    {
        config()->set('gone', 1);

        $this->config->withLogging()->remove('gone');

        self::assertSame([['operation' => 'remove', 'key' => 'gone']], $this->config->getLog());
    }

    // -----------------------------------------------------------------
    // File operations
    // -----------------------------------------------------------------

    #[Test]
    public function it_loads_and_overrides_from_a_file(): void
    {
        file_put_contents(
            $this->sandbox . '/config/packages/widgets.php',
            '<?php return ["enabled" => true, "limit" => 5];',
        );

        $this->config->setBasePath($this->sandbox)->loadPackageConfig('widgets');

        self::assertTrue($this->config->get('widgets.enabled'));
        self::assertSame(5, $this->config->get('widgets.limit'));
    }

    #[Test]
    public function a_missing_file_throws(): void
    {
        $this->expectException(InvalidPath::class);

        $this->config->setBasePath($this->sandbox)->loadPackageConfig('absent');
    }

    #[Test]
    public function a_file_that_is_not_an_array_throws(): void
    {
        file_put_contents($this->sandbox . '/config/packages/bad.php', '<?php return "nope";');

        $this->expectException(InvalidPath::class);

        $this->config->setBasePath($this->sandbox)->loadPackageConfig('bad');
    }

    #[Test]
    public function load_config_file_is_lenient_about_a_missing_file(): void
    {
        self::assertSame([], $this->config->setBasePath($this->sandbox)->loadConfigFile('nothing'));
    }

    #[Test]
    public function load_config_file_reads_the_config_directory(): void
    {
        file_put_contents($this->sandbox . '/config/app.php', '<?php return ["name" => "Sandbox"];');

        $loaded = $this->config->setBasePath($this->sandbox)->loadConfigFile('app');

        self::assertSame(['name' => 'Sandbox'], $loaded);
        self::assertSame(['name' => 'Sandbox'], $this->config->loadConfigFile('app.php'));
    }

    #[Test]
    public function an_absolute_path_ignores_the_base_path(): void
    {
        $absolute = $this->sandbox . '/elsewhere.php';
        file_put_contents($absolute, '<?php return ["k" => "v"];');

        $this->config->setBasePath('/nonexistent')->loadAndOverride('abs', $absolute);

        self::assertSame('v', $this->config->get('abs.k'));
    }

    // -----------------------------------------------------------------
    // Container wiring
    // -----------------------------------------------------------------

    #[Test]
    public function the_container_hands_out_a_fresh_instance_each_time(): void
    {
        $a = $this->app->make(ConfigManagerInterface::class);
        $b = $this->app->make(ConfigManagerInterface::class);

        self::assertInstanceOf(ConfigManager::class, $a);
        // Stateful: a base path set by one caller must not leak to another.
        self::assertNotSame($a, $b);
    }
}

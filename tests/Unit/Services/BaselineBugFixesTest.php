<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services;

use Illuminate\Contracts\Http\Kernel;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\PackageTools\Services\Asset\AssetRegistry;
use Simtabi\Laranail\PackageTools\Services\Component\ComponentRegistry;
use Simtabi\Laranail\PackageTools\Services\Config\ConfigFileResolver;
use Simtabi\Laranail\PackageTools\Services\Event\MiddlewareRegistry;
use Simtabi\Laranail\PackageTools\Tests\TestCase;

/**
 * Regression tests for the call-site corrections that were previously masked
 * by the PHPStan baseline. Each test proves that the method the fixed call
 * site now targets behaves the way the caller intended.
 */
class BaselineBugFixesTest extends TestCase
{
    /**
     * Bug 5a: HasSafeComponentRegistration / HasVueComponents now route through
     * ComponentRegistry::register("type::name", $class) and registerVue().
     */
    #[Test]
    public function component_registry_register_routes_by_key_prefix(): void
    {
        $registry = new ComponentRegistry;

        $registry->register('vue::alert', '/path/to/Alert.vue');

        $this->assertSame(
            ['alert' => '/path/to/Alert.vue'],
            $registry->getByType('vue'),
        );
    }

    #[Test]
    public function component_registry_register_vue_stores_the_path(): void
    {
        $registry = new ComponentRegistry;

        $registry->registerVue('button', '/path/to/Button.vue');

        $this->assertSame('/path/to/Button.vue', $registry->getByType('vue')['button']);
    }

    /**
     * Bug 5b: HasEnhancedMiddleware now calls registerGroup()/get($key, $default)
     * instead of the non-existent 3-argument forms.
     */
    #[Test]
    public function middleware_registry_register_group_and_get_round_trip(): void
    {
        $registry = new MiddlewareRegistry($this->app->make(Kernel::class));

        $registry->registerGroup('web-blog', ['MiddlewareA']);

        $this->assertSame(['MiddlewareA'], $registry->get('web-blog'));

        // Mirror addToMiddlewareGroup(): read current, append, re-register.
        $current = $registry->get('web-blog', []);
        $current[] = 'MiddlewareB';
        $registry->registerGroup('web-blog', $current);

        $this->assertSame(['MiddlewareA', 'MiddlewareB'], $registry->get('web-blog'));
    }

    #[Test]
    public function middleware_registry_get_returns_default_for_missing_key(): void
    {
        $registry = new MiddlewareRegistry($this->app->make(Kernel::class));

        $this->assertSame([], $registry->get('missing', []));
    }

    /**
     * Bug 5c: HasNestedConfigFiles now calls resolveNested($file, $folder)
     * rather than passing an array as a phantom second arg to resolve().
     */
    #[Test]
    public function config_file_resolver_resolves_nested_folder(): void
    {
        $resolver = new ConfigFileResolver('/var/www/pkg');

        $resolved = $resolver->resolveNested('settings', 'admin');

        $this->assertStringContainsString('config', $resolved);
        $this->assertStringContainsString('admin', $resolved);
        $this->assertStringEndsWith('settings.php', $resolved);
    }

    #[Test]
    public function config_file_resolver_nested_falls_back_to_flat_when_folder_empty(): void
    {
        $resolver = new ConfigFileResolver('/var/www/pkg');

        $this->assertSame(
            $resolver->resolve('settings'),
            $resolver->resolveNested('settings', ''),
        );
    }

    /**
     * Bug 2: HasAssetCleanup::cleanupAllAssets() iterates the registry via the
     * real accessor (getRegistered()) rather than a non-existent all().
     */
    #[Test]
    public function asset_registry_get_registered_exposes_all_tags(): void
    {
        $registry = new AssetRegistry;

        $registry->register('blog-assets', '/public/vendor/blog', true);
        $registry->register('blog-icons', '/public/vendor/icons', true);

        $tags = array_keys($registry->getRegistered());

        sort($tags);
        $this->assertSame(['blog-assets', 'blog-icons'], $tags);
    }
}

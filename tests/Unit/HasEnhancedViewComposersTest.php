<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasEnhancedViewComposers;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * HasEnhancedViewComposersTest - Test enhanced view composer registration
 *
 * Covers Phase 6 view composer features
 */
class HasEnhancedViewComposersTest extends TestCase
{
    use HasEnhancedViewComposers;

    #[Test]
    public function it_registers_single_view_composer(): void
    {
        $this->registerViewComposer('dashboard', 'DashboardComposer', autoPrefix: false);

        $composers = $this->getViewComposerRegistry();

        $this->assertArrayHasKey('dashboard', $composers);
        $this->assertContains('DashboardComposer', $composers['dashboard']);
    }

    #[Test]
    public function it_registers_multiple_views_for_one_composer(): void
    {
        $this->registerViewComposer(['index', 'show'], 'ListComposer', autoPrefix: false);

        $composers = $this->getViewComposerRegistry();

        $this->assertArrayHasKey('index', $composers);
        $this->assertArrayHasKey('show', $composers);
    }

    #[Test]
    public function it_registers_wildcard_pattern(): void
    {
        $this->registerViewComposer('blog.*', 'BlogComposer', autoPrefix: false);

        $composers = $this->getViewComposerRegistry();

        $this->assertArrayHasKey('blog.*', $composers);
    }

    #[Test]
    public function it_supports_multiple_composers_per_view(): void
    {
        $this->registerViewComposer('dashboard', 'FirstComposer', autoPrefix: false);
        $this->registerViewComposer('dashboard', 'SecondComposer', autoPrefix: false);

        $composers = $this->getViewComposerRegistry();

        $this->assertCount(2, $composers['dashboard']);
    }

    #[Test]
    public function it_auto_prefixes_view_names_when_enabled(): void
    {
        $this->registerViewComposer('dashboard', 'DashboardComposer');

        $composers = $this->getViewComposerRegistry();

        $this->assertArrayHasKey('test::dashboard', $composers);
    }

    #[Test]
    public function it_does_not_double_prefix(): void
    {
        $this->registerViewComposer('test::dashboard', 'DashboardComposer');

        $composers = $this->getViewComposerRegistry();

        $this->assertArrayHasKey('test::dashboard', $composers);
        $this->assertArrayNotHasKey('test::test::dashboard', $composers);
    }

    #[Test]
    public function it_can_disable_auto_prefix(): void
    {
        $this->disableViewComposerAutoPrefix();
        $this->registerViewComposer('dashboard', 'DashboardComposer');

        $composers = $this->getViewComposerRegistry();

        $this->assertArrayHasKey('dashboard', $composers);
        $this->assertArrayNotHasKey('test::dashboard', $composers);
    }

    #[Test]
    public function it_chains_registrations(): void
    {
        $result = $this->registerViewComposer('dashboard', 'First', autoPrefix: false)
            ->registerViewComposer('profile', 'Second', autoPrefix: false);

        $this->assertInstanceOf(static::class, $result);
        $this->assertCount(2, $this->getViewComposerRegistry());
    }

    #[Test]
    public function it_supports_fluent_api(): void
    {
        $this->registerViewComposer('view1', 'Composer1', autoPrefix: false)
            ->registerViewComposer('view2', 'Composer2', autoPrefix: false)
            ->disableViewComposerAutoPrefix();

        $this->assertCount(2, $this->getViewComposerRegistry());
    }

    #[Test]
    public function it_returns_empty_registry_when_nothing_registered(): void
    {
        $this->assertEmpty($this->getViewComposerRegistry());
    }

    #[Test]
    public function it_accepts_a_callable_composer(): void
    {
        $callable = fn (): null => null;

        $this->registerViewComposer('dashboard', $callable, autoPrefix: false);

        $composers = $this->getViewComposerRegistry();

        $this->assertArrayHasKey('dashboard', $composers);
        $this->assertSame($callable, $composers['dashboard'][0]);
    }

    #[Test]
    public function it_registers_view_composers_in_bulk(): void
    {
        $this->registerViewComposers([
            'dashboard' => 'DashboardComposer',
            'profile' => 'ProfileComposer',
        ], autoPrefix: false);

        $composers = $this->getViewComposerRegistry();

        $this->assertContains('DashboardComposer', $composers['dashboard']);
        $this->assertContains('ProfileComposer', $composers['profile']);
    }

    #[Test]
    public function it_registers_a_global_view_composer(): void
    {
        $this->registerGlobalViewComposer('GlobalComposer');

        $composers = $this->getViewComposerRegistry();

        $this->assertArrayHasKey('*', $composers);
        $this->assertContains('GlobalComposer', $composers['*']);
        $this->assertArrayNotHasKey('test::*', $composers);
    }

    #[Test]
    public function it_registers_a_view_creator(): void
    {
        $this->registerViewCreator('dashboard', 'DashboardCreator', autoPrefix: false);

        $creators = $this->getViewCreatorRegistry();

        $this->assertArrayHasKey('dashboard', $creators);
        $this->assertContains('DashboardCreator', $creators['dashboard']);
    }

    #[Test]
    public function it_auto_prefixes_view_creators(): void
    {
        $this->registerViewCreator('dashboard', 'DashboardCreator');

        $creators = $this->getViewCreatorRegistry();

        $this->assertArrayHasKey('test::dashboard', $creators);
    }

    // Helper for trait
    protected function getViewNamespace(): string
    {
        return 'test';
    }
}

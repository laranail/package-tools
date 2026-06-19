<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\PackageTools\Concerns\Package\HasEnhancedViewComposers;
use Simtabi\Laranail\PackageTools\Tests\TestCase;

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

    // Helper for trait
    protected function getViewNamespace(): string
    {
        return 'test';
    }
}

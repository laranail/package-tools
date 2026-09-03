<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Simtabi\Laranail\Package\Tools\Tests\ParallelSafe;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\WidgetServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\AssertsPublishedConfigOverrides;

/**
 * The reusable testing trait must reliably round-trip a published override —
 * write the file, register a fresh provider, assert, and clean up.
 */
class AssertsPublishedConfigOverridesTest extends TestCase
{
    use AssertsPublishedConfigOverrides;

    /**
     * This test publishes into the application's config directory, and every
     * parallel worker shares one skeleton - so it gets its own copy. See
     * ParallelSafe::isolatedSkeleton().
     */
    public static function applicationBasePath(): string
    {
        return ParallelSafe::isolatedSkeleton();
    }

    #[Test]
    public function the_trait_round_trips_a_published_override(): void
    {
        $this->assertPublishedConfigOverride(
            WidgetServiceProvider::class,
            ParallelSafe::vendor() . '.widget',
            ['enabled' => false, 'extra' => 'from-trait'],
            ParallelSafe::vendor() . '.widget.enabled',
            false,
        );
    }

    #[Test]
    public function the_trait_cleans_up_the_published_file_afterwards(): void
    {
        $this->assertPublishedConfigOverride(
            WidgetServiceProvider::class,
            ParallelSafe::vendor() . '.widget',
            ['enabled' => false],
            ParallelSafe::vendor() . '.widget.enabled',
            false,
        );

        // The helper deletes the file in its finally block.
        $this->assertFileDoesNotExist(config_path(ParallelSafe::vendor() . '/widget.php'));
    }
}

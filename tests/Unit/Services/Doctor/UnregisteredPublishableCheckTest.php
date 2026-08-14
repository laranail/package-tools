<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Doctor;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Services\Asset\PublishTagRegistry;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\UnregisteredPublishableCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorStatus;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * The failure this reports is silent by construction: a module whose provider
 * forgot `setPublishTagId()` works perfectly and simply never publishes, so the
 * symptom arrives later and somewhere else — a missing stylesheet, a config the
 * operator believes they already published.
 *
 * The command this idea comes from scanned the same directories and
 * **published** each one under a guessed tag. This reads and reports.
 */
final class UnregisteredPublishableCheckTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/pt-publish-cov-' . bin2hex(random_bytes(6));
        mkdir($this->sandbox . '/modules', 0o777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->sandbox));

        parent::tearDown();
    }

    private function makeModule(string $name): void
    {
        mkdir($this->sandbox . '/modules/' . $name, 0o777, true);
    }

    private function registry(string ...$packages): PublishTagRegistry
    {
        $registry = new PublishTagRegistry;

        foreach ($packages as $i => $package) {
            $registry->record('tag-' . $i, $package, ['/tmp/whatever'], true);
        }

        return $registry;
    }

    private function check(PublishTagRegistry $registry, ?array $directories = null): UnregisteredPublishableCheck
    {
        return new UnregisteredPublishableCheck(
            $registry,
            $directories ?? [$this->sandbox . '/modules'],
        );
    }

    #[Test]
    public function it_passes_when_every_directory_registered_something(): void
    {
        $this->makeModule('blog');
        $this->makeModule('shop');

        $result = $this->check($this->registry('acme/blog', 'acme/shop'))->run();

        self::assertSame(DoctorStatus::Pass, $result->status);
    }

    #[Test]
    public function it_warns_about_a_directory_that_registered_nothing(): void
    {
        $this->makeModule('blog');
        $this->makeModule('forgotten');

        $result = $this->check($this->registry('acme/blog'))->run();

        self::assertSame(DoctorStatus::Warn, $result->status);
        self::assertStringContainsString('1 package directories', $result->message);
        self::assertContains('forgotten', $result->detail['directories'] ?? []);
    }

    #[Test]
    public function it_warns_rather_than_failing(): void
    {
        // A module that is all routes and controllers has nothing to publish,
        // and that is perfectly ordinary. This reports a discrepancy for a
        // human to judge rather than deciding it is a defect.
        $this->makeModule('routes-only');

        self::assertSame(DoctorStatus::Warn, $this->check($this->registry())->run()->status);
    }

    #[Test]
    public function it_matches_a_directory_name_against_the_package_short_name(): void
    {
        // The directory is `blog`; the package is `acme/blog`.
        $this->makeModule('blog');

        self::assertSame(DoctorStatus::Pass, $this->check($this->registry('acme/blog'))->run()->status);
    }

    #[Test]
    public function it_matches_case_insensitively(): void
    {
        $this->makeModule('Blog');

        self::assertSame(DoctorStatus::Pass, $this->check($this->registry('acme/blog'))->run()->status);
    }

    #[Test]
    public function it_skips_when_no_directories_are_configured(): void
    {
        // Skip, not pass: it checked nothing, and reporting a pass would mean
        // "everything is registered" when nothing was looked at.
        $result = $this->check($this->registry(), [])->run();

        self::assertSame(DoctorStatus::Skip, $result->status);
    }

    #[Test]
    public function it_skips_when_the_configured_directories_do_not_exist(): void
    {
        $result = $this->check($this->registry(), ['/nonexistent/modules'])->run();

        self::assertSame(DoctorStatus::Skip, $result->status);
    }

    #[Test]
    public function it_scans_several_directories(): void
    {
        mkdir($this->sandbox . '/packages/extra', 0o777, true);
        $this->makeModule('blog');

        $result = $this->check(
            $this->registry('acme/blog'),
            [$this->sandbox . '/modules', $this->sandbox . '/packages'],
        )->run();

        self::assertSame(DoctorStatus::Warn, $result->status);
        self::assertSame(['extra'], $result->detail['directories'] ?? []);
    }

    #[Test]
    public function it_writes_nothing(): void
    {
        // The whole point of the shape change: the command this replaces
        // published files to answer this question.
        $this->makeModule('forgotten');
        $before = scandir($this->sandbox . '/modules/forgotten');

        $this->check($this->registry())->run();

        self::assertSame($before, scandir($this->sandbox . '/modules/forgotten'));
    }
}

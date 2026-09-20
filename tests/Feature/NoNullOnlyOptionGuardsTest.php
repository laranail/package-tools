<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\AssertionFailedError;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Simtabi\Laranail\Package\Tools\Testing\AssertsDriverContract;

/**
 * The guard is checked for teeth against fixtures that reproduce the two shapes
 * `laranail/license-verifier` shipped, rather than against a package that has
 * already been fixed -- a guard is only worth keeping if a regression makes it
 * fail, and the only way to know that is to write the regression.
 */
final class NoNullOnlyOptionGuardsTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = sys_get_temp_dir() . '/laranail-optguard-' . bin2hex(random_bytes(6));
        mkdir($this->sandbox, 0o777, true);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->sandbox . '/*.php') ?: []);
        @rmdir($this->sandbox);
        parent::tearDown();
    }

    #[Test]
    public function it_flags_a_ternary_that_tests_only_for_null(): void
    {
        // license-verifier's ReminderCommand, verbatim in shape.
        $this->write('ReminderCommand.php', '$days = $this->option(\'days\') !== null ? (int) $this->option(\'days\') : null;');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/ReminderCommand\.php:\d+/');

        $this->subject()->run($this->sandbox);
    }

    #[Test]
    public function it_flags_a_null_coalesce_default(): void
    {
        // `??` catches null and lets '' through, which is the same defect wearing
        // a guard that looks complete.
        $this->write('IssueCommand.php', '$kid = $this->option(\'kid\') ?? \'signing-\' . bin2hex(random_bytes(16));');

        $this->expectException(AssertionFailedError::class);

        $this->subject()->run($this->sandbox);
    }

    #[Test]
    public function it_accepts_a_coalesce_whose_default_is_the_empty_string(): void
    {
        // `?? ''` is correct as written: the two cases coincide.
        $this->write('ExportCommand.php', '$out = (string) ($this->option(\'out\') ?? \'\');');

        $this->subject()->run($this->sandbox);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_accepts_the_normalising_accessors(): void
    {
        $this->write('WatchCommand.php', "\$cycles = \$this->intOption('cycles');\n\$name = \$this->strOption('name');\n\$x = \$this->option('raw');");

        $this->subject()->run($this->sandbox);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_accepts_a_line_annotated_with_the_exemption_marker(): void
    {
        // For a null-only test whose body validates the empty case and fails closed.
        $this->write('IssueCommand.php', "\$x = \$this->option('days') !== null; // @option-guard-exempt validated below");

        $this->subject()->run($this->sandbox);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function the_marker_may_sit_in_the_comment_block_above_the_line(): void
    {
        // Where the rationale is a sentence rather than a trailing note.
        $this->write('IssueCommand.php', "// @option-guard-exempt -- validated below, fails closed.\n\$x = \$this->option('days') !== null;");

        $this->subject()->run($this->sandbox);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function an_exemption_marker_covers_only_the_line_it_is_on(): void
    {
        // The reason the marker is per-line: a whole-file skip also covers reads
        // added to that file later, which is how a guard stops guarding.
        $this->write('IssueCommand.php', "\$a = \$this->option('days') !== null; // @option-guard-exempt\n\$b = \$this->option('kid') ?? 'generated';");

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/IssueCommand\.php:\d+/');

        $this->subject()->run($this->sandbox);
    }

    #[Test]
    public function it_ignores_a_class_that_merely_has_its_own_option_accessor(): void
    {
        // `$this->option()` is not exclusively the console's. laranail/captcha's ReCaptcha
        // adapters read a widget's options through a method of that name and extend
        // SiteVerifyAdapter -- flagging them is the wrong file, not an exemption to annotate.
        $this->writeRaw('EnterpriseAdapter.php', "class EnterpriseAdapter extends SiteVerifyAdapter {\n  public function build() {\n    return \$this->option('language') !== null ? \$this->option('language') : 'en';\n  }\n}");
        $this->writeRaw('RealCommand.php', "class RealCommand extends Command {\n  protected \$signature = 'x';\n  public function handle() { return \$this->option('a'); }\n}");

        $this->subject()->run($this->sandbox);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_command_shaped_file_is_still_scanned(): void
    {
        $this->writeRaw('RealCommand.php', "class RealCommand extends Command {\n  protected \$signature = 'x';\n  public function handle() { return \$this->option('a') ?? 'fallback'; }\n}");

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/RealCommand\.php/');

        $this->subject()->run($this->sandbox);
    }

    #[Test]
    public function it_refuses_to_pass_vacuously_when_nothing_reads_an_option(): void
    {
        // A scan that finds no option read at all is a statement about an empty
        // set, and it reads as a guarantee.
        $this->write('NotACommand.php', '$x = 1;');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/proved nothing/');

        $this->subject()->run($this->sandbox);
    }

    /**
     * Writes a COMMAND-shaped file, because that is what the guard scans -- a bare
     * statement is skipped by design, and a fixture that is skipped proves nothing.
     */
    private function write(string $name, string $body): void
    {
        $class = basename($name, '.php');
        $this->writeRaw($name, "class {$class} extends Command\n{\n    protected \$signature = 'x';\n\n    public function handle(): int\n    {\n        {$body}\n\n        return 0;\n    }\n}");
    }

    private function writeRaw(string $name, string $body): void
    {
        file_put_contents($this->sandbox . '/' . $name, "<?php\n" . $body);
    }

    private function subject(): object
    {
        return new class
        {
            use AssertsDriverContract {
                assertNoNullOnlyOptionGuards as public run;
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Feature\Commands;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Console\Command as BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Simtabi\Laranail\Package\Tools\Commands\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\ReadsOptions;

/**
 * Driven as a real command rather than against a stubbed `option()`, because
 * every case this trait exists for -- `''` from `--flag=`, `'false'` from
 * `--flag=false`, an array from a repeatable option -- is produced by Symfony's
 * input parsing. A stub reproduces none of them, and would assert only that the
 * casts do what casts do.
 */
final class ReadsOptionsCommand extends BaseCommand
{
    use ReadsOptions;

    protected $signature = 'laranail-test:reads-options
        {name? : An optional argument}
        {--connection= : A scalar option}
        {--tables= : A comma-separated option}
        {--limit= : A numeric option}
        {--force= : A flag declared WITH a value, so --force=false is expressible}
        {--quiet-mode : A flag declared with no value}
        {--tag=* : A repeatable option}';

    protected $description = 'Option-normalisation test command';

    public function handle(): int
    {
        return self::SUCCESS;
    }

    public function readStrOption(string $key): ?string
    {
        return $this->strOption($key);
    }

    public function readStringOption(string $key, string $default = ''): string
    {
        return $this->stringOption($key, $default);
    }

    public function readStrArg(string $key): string
    {
        return $this->strArg($key);
    }

    public function readBoolOption(string $key): bool
    {
        return $this->boolOption($key);
    }

    public function readIntOption(string $key, ?int $default = null): ?int
    {
        return $this->intOption($key, $default);
    }

    /** @return list<string> */
    public function readArrayOption(string $key): array
    {
        return $this->arrayOption($key);
    }

    /** @return list<string> */
    public function readListOption(string $key): array
    {
        return $this->listOption($key);
    }
}

final class ReadsOptionsTest extends TestCase
{
    // --- strOption: absence preserved -------------------------------------

    #[Test]
    public function a_supplied_option_is_returned_verbatim(): void
    {
        self::assertSame('pgsql', $this->dispatch(['--connection' => 'pgsql'])->readStrOption('connection'));
    }

    #[Test]
    public function an_absent_option_is_null(): void
    {
        self::assertNull($this->dispatch([])->readStrOption('connection'));
    }

    /**
     * `--connection=` parses to `''`, which is not a usable connection name.
     * Reporting it as supplied forks every cache keyed on the resolved value.
     */
    #[Test]
    public function an_option_given_without_a_value_is_null_not_empty_string(): void
    {
        self::assertNull($this->dispatch(['--connection' => ''])->readStrOption('connection'));
    }

    // --- stringOption: a guaranteed string --------------------------------

    #[Test]
    public function a_string_option_falls_back_to_the_default(): void
    {
        self::assertSame('table', $this->dispatch([])->readStringOption('connection', 'table'));
    }

    #[Test]
    public function a_repeatable_option_collapses_to_its_first_element(): void
    {
        self::assertSame('a', $this->dispatch(['--tag' => ['a', 'b']])->readStringOption('tag'));
    }

    /**
     * The two string accessors disagree on purpose, and this pins the
     * disagreement: for `--connection=` one says "nothing", the other says
     * "here is your default".
     */
    #[Test]
    public function the_two_string_accessors_differ_on_an_option_given_without_a_value(): void
    {
        $command = $this->dispatch(['--connection' => '']);

        self::assertNull($command->readStrOption('connection'));
        self::assertSame('fallback', $command->readStringOption('connection', 'fallback'));
    }

    // --- strArg ------------------------------------------------------------

    #[Test]
    public function a_supplied_argument_is_returned_verbatim(): void
    {
        self::assertSame('users', $this->dispatch(['name' => 'users'])->readStrArg('name'));
    }

    #[Test]
    public function an_absent_argument_is_an_empty_string(): void
    {
        self::assertSame('', $this->dispatch([])->readStrArg('name'));
    }

    // --- boolOption --------------------------------------------------------

    #[Test]
    public function a_declared_flag_is_true_when_present_and_false_when_absent(): void
    {
        self::assertTrue($this->dispatch(['--quiet-mode' => true])->readBoolOption('quiet-mode'));
        self::assertFalse($this->dispatch([])->readBoolOption('quiet-mode'));
    }

    /**
     * The reason boolOption() exists. `'false'` is a non-empty string, so a
     * plain `(bool)` cast returns TRUE -- the flag reads as set exactly when
     * the caller said not to.
     */
    #[Test]
    public function a_flag_written_false_is_false_not_true(): void
    {
        self::assertFalse($this->dispatch(['--force' => 'false'])->readBoolOption('force'));
        self::assertTrue((bool) 'false', 'the cast this method replaces is wrong here');
    }

    #[Test]
    public function the_recognised_false_spellings_are_all_false(): void
    {
        foreach (['false', '0', 'no', 'off'] as $spelling) {
            self::assertFalse(
                $this->dispatch(['--force' => $spelling])->readBoolOption('force'),
                "--force={$spelling} should be false",
            );
        }
    }

    #[Test]
    public function an_unrecognised_value_counts_as_true_because_the_flag_was_written(): void
    {
        self::assertTrue($this->dispatch(['--force' => 'sure'])->readBoolOption('force'));
    }

    // --- intOption ---------------------------------------------------------

    #[Test]
    public function a_numeric_option_is_an_int(): void
    {
        self::assertSame(25, $this->dispatch(['--limit' => '25'])->readIntOption('limit'));
    }

    #[Test]
    public function an_absent_numeric_option_is_the_default(): void
    {
        self::assertSame(10, $this->dispatch([])->readIntOption('limit', 10));
        self::assertNull($this->dispatch([])->readIntOption('limit'));
    }

    /**
     * `(int) 'twenty'` is `0`, which for --limit, --port or --timeout looks
     * deliberate and passes every downstream check.
     */
    #[Test]
    public function a_non_numeric_option_is_the_default_not_zero(): void
    {
        self::assertSame(10, $this->dispatch(['--limit' => 'twenty'])->readIntOption('limit', 10));
        self::assertSame(0, (int) 'twenty', 'the cast this method replaces is wrong here');
    }

    // --- arrayOption -------------------------------------------------------

    #[Test]
    public function a_repeatable_option_is_a_list(): void
    {
        self::assertSame(['a', 'b'], $this->dispatch(['--tag' => ['a', 'b']])->readArrayOption('tag'));
    }

    #[Test]
    public function a_repeatable_option_is_trimmed_and_compacted(): void
    {
        self::assertSame(['a', 'b'], $this->dispatch(['--tag' => [' a ', '', 'b']])->readArrayOption('tag'));
    }

    #[Test]
    public function an_absent_repeatable_option_is_an_empty_list(): void
    {
        self::assertSame([], $this->dispatch([])->readArrayOption('tag'));
    }

    // --- listOption --------------------------------------------------------

    #[Test]
    public function a_list_option_is_split_trimmed_and_compacted(): void
    {
        self::assertSame(
            ['users', 'jobs', 'sessions'],
            $this->dispatch(['--tables' => 'users, jobs ,,  sessions '])->readListOption('tables'),
        );
    }

    #[Test]
    public function an_absent_or_empty_list_option_is_an_empty_list(): void
    {
        self::assertSame([], $this->dispatch([])->readListOption('tables'));
        self::assertSame([], $this->dispatch(['--tables' => ''])->readListOption('tables'));
    }

    // --- the base class contract ------------------------------------------

    /**
     * `stringOption()` was a method on the base class before it moved into this
     * trait. Moving it must not change what a subclass sees.
     */
    #[Test]
    public function the_base_command_still_exposes_every_accessor(): void
    {
        foreach (['stringOption', 'strOption', 'strArg', 'boolOption', 'intOption', 'arrayOption', 'listOption'] as $method) {
            self::assertTrue(
                method_exists(Command::class, $method),
                "the base Command must still expose {$method}()",
            );
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    private function dispatch(array $input): ReadsOptionsCommand
    {
        $command = new ReadsOptionsCommand;
        $command->setLaravel($this->app);
        $command->run(new ArrayInput($input), new BufferedOutput);

        return $command;
    }
}

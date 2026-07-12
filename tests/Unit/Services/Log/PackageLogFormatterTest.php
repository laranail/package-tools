<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Log;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogFormatter;

final class PackageLogFormatterTest extends TestCase
{
    private function record(
        string $message,
        Level $level = Level::Info,
        array $context = [],
    ): LogRecord {
        return new LogRecord(
            datetime: new DateTimeImmutable('2026-07-08 14:03:22.512'),
            channel: 'acme-blog',
            level: $level,
            message: $message,
            context: $context,
        );
    }

    #[Test]
    public function it_renders_the_bracketed_prefix(): void
    {
        $line = (new PackageLogFormatter('acme/blog'))->format($this->record('Blog routes registered'));

        $this->assertSame(
            "[2026-07-08 14:03:22.512] [acme/blog] [INFO] Blog routes registered\n",
            $line,
        );
    }

    #[Test]
    public function the_label_bracket_appears_only_when_a_label_is_set(): void
    {
        $formatter = new PackageLogFormatter('acme/blog');

        $unlabeled = $formatter->format($this->record('plain'));
        $labeled = $formatter->format($this->record('with label', context: [
            PackageLogFormatter::LABEL_KEY => 'Install',
        ]));

        $this->assertStringNotContainsString('[Install]', $unlabeled);
        $this->assertStringContainsString('[INFO] [Install] with label', $labeled);
    }

    #[Test]
    public function success_marker_replaces_the_level_token_and_is_stripped_from_the_tail(): void
    {
        $line = (new PackageLogFormatter('acme/blog'))->format($this->record('Migrations published', context: [
            PackageLogFormatter::LEVEL_LABEL_KEY => 'SUCCESS',
            'count' => 3,
        ]));

        $this->assertStringContainsString('[SUCCESS]', $line);
        $this->assertStringNotContainsString('INFO', $line);
        $this->assertStringNotContainsString('_level_label', $line);
        $this->assertStringContainsString('| {"count":3}', $line);
    }

    #[Test]
    public function empty_context_produces_no_tail(): void
    {
        $line = (new PackageLogFormatter('acme/blog'))->format($this->record('bare'));

        $this->assertStringNotContainsString('|', $line);
        $this->assertStringNotContainsString('{}', $line);
    }

    #[Test]
    public function the_context_tail_is_valid_json(): void
    {
        $line = (new PackageLogFormatter('acme/blog'))->format($this->record('with data', context: [
            'string' => 'value',
            'number' => 42,
            'nested' => ['a' => true],
        ]));

        [, $tail] = explode(' | ', trim($line), 2);
        $decoded = json_decode($tail, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['string' => 'value', 'number' => 42, 'nested' => ['a' => true]], $decoded);
    }

    #[Test]
    public function throwables_in_context_are_normalized_not_fatal(): void
    {
        $line = (new PackageLogFormatter('acme/blog'))->format($this->record('failed', Level::Error, [
            'exception' => new RuntimeException('boom'),
        ]));

        $this->assertStringContainsString('[ERROR]', $line);
        $this->assertStringContainsString('RuntimeException', $line);
        $this->assertStringContainsString('boom', $line);
    }

    #[Test]
    public function buffered_time_overrides_the_record_timestamp(): void
    {
        $line = (new PackageLogFormatter('acme/blog'))->format($this->record('early line', context: [
            PackageLogFormatter::TIME_KEY => new DateTimeImmutable('2026-07-08 09:00:00.001'),
        ]));

        $this->assertStringStartsWith('[2026-07-08 09:00:00.001]', $line);
        $this->assertStringNotContainsString('_time', $line);
    }

    #[Test]
    public function multiline_messages_collapse_to_one_line(): void
    {
        $line = (new PackageLogFormatter('acme/blog'))->format($this->record("first\nsecond\rthird"));

        $this->assertSame(1, substr_count($line, "\n"));
        $this->assertStringContainsString('first second third', $line);
    }
}

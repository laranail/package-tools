<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Environment;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Services\Environment\EnvFileParser;

final class EnvFileParserTest extends TestCase
{
    public function test_parses_simple_key_value(): void
    {
        $parsed = (new EnvFileParser)->parse("APP_NAME=Laravel\nAPP_ENV=local\n");

        $this->assertSame('Laravel', $parsed['APP_NAME']);
        $this->assertSame('local', $parsed['APP_ENV']);
    }

    public function test_ignores_blank_lines_and_comments(): void
    {
        $contents = <<<'ENV_WRAP'
        
        # leading comment
        APP_NAME=Laravel
        
        # mid comment
        APP_ENV=local
        ENV_WRAP;

        $parsed = (new EnvFileParser)->parse($contents);

        $this->assertSame(['APP_NAME' => 'Laravel', 'APP_ENV' => 'local'], $parsed);
    }

    public function test_handles_double_quoted_values(): void
    {
        $parsed = (new EnvFileParser)->parse('APP_NAME="My App with spaces"');
        $this->assertSame('My App with spaces', $parsed['APP_NAME']);
    }

    public function test_handles_single_quoted_values_without_interpolation(): void
    {
        $parsed = (new EnvFileParser)->parse("OTHER=World\nGREET='Hello \${OTHER}'");
        $this->assertSame('Hello ${OTHER}', $parsed['GREET']);
    }

    public function test_strips_inline_comments_on_unquoted_values(): void
    {
        $parsed = (new EnvFileParser)->parse("APP_DEBUG=true # only in dev\n");
        $this->assertSame('true', $parsed['APP_DEBUG']);
    }

    public function test_does_not_strip_hash_inside_double_quotes(): void
    {
        $parsed = (new EnvFileParser)->parse('PASSWORD="hunter2#secret"');
        $this->assertSame('hunter2#secret', $parsed['PASSWORD']);
    }

    public function test_supports_variable_interpolation(): void
    {
        $contents = "APP_HOST=example.com\nAPP_URL=https://\${APP_HOST}/api\n";

        $parsed = (new EnvFileParser)->parse($contents);

        $this->assertSame('https://example.com/api', $parsed['APP_URL']);
    }

    public function test_interpolation_is_bounded_and_does_not_loop_forever(): void
    {
        // Forward reference (B not yet defined when A is parsed) → empty.
        $contents = "A=\${B}\nB=value\n";

        $parsed = (new EnvFileParser)->parse($contents);

        $this->assertSame('', $parsed['A']);
        $this->assertSame('value', $parsed['B']);
    }

    public function test_last_definition_wins_for_duplicates(): void
    {
        $parsed = (new EnvFileParser)->parse("KEY=first\nKEY=second\n");
        $this->assertSame('second', $parsed['KEY']);
    }

    public function test_skips_invalid_keys(): void
    {
        $parsed = (new EnvFileParser)->parse("9KEY=nope\nVALID=yes\n");
        $this->assertArrayNotHasKey('9KEY', $parsed);
        $this->assertSame('yes', $parsed['VALID']);
    }

    public function test_handles_crlf_line_endings(): void
    {
        $parsed = (new EnvFileParser)->parse("KEY1=a\r\nKEY2=b\r\n");
        $this->assertSame(['KEY1' => 'a', 'KEY2' => 'b'], $parsed);
    }

    public function test_keys_returns_declaration_order(): void
    {
        $keys = (new EnvFileParser)->keys("ZZ=1\nAA=2\nMM=3\n");
        $this->assertSame(['ZZ', 'AA', 'MM'], $keys);
    }

    public function test_handles_empty_value(): void
    {
        $parsed = (new EnvFileParser)->parse("APP_KEY=\n");
        $this->assertSame('', $parsed['APP_KEY']);
    }
}

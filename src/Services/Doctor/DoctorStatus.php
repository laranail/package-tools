<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor;

use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;

enum DoctorStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
    case Skip = 'skip';

    public function symbol(): string
    {
        return match ($this) {
            self::Pass => '✓',
            self::Warn => '!',
            self::Fail => '✗',
            self::Skip => '·',
        };
    }

    /**
     * The glyph for this status: laranail/console's shared symbol when console
     * is installed (so it follows the terminal's Unicode/ASCII capability, like
     * every other status in the family), this package's own otherwise.
     */
    public function glyph(): string
    {
        $symbols = Symbols::class;
        $capabilities = Capabilities::class;

        if (! class_exists($symbols) || ! class_exists($capabilities)) {
            return $this->symbol();
        }

        return $symbols::for($capabilities::detect())->get(match ($this) {
            self::Pass => 'success',
            self::Warn => 'warning',
            self::Fail => 'error',
            self::Skip => 'skipped',
        });
    }

    /**
     * The Symfony formatter colour for this status, for `<fg=…>` markup. Unlike
     * a raw escape sequence, markup is dropped under --no-ansi and when output
     * is piped.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pass => 'green',
            self::Warn => 'yellow',
            self::Fail => 'red',
            self::Skip => 'gray',
        };
    }

    /**
     * @deprecated Use {@see color()} with `<fg=…>` markup. A raw escape sequence
     *             ignores --no-ansi and leaks into piped output.
     */
    public function ansiColor(): string
    {
        return match ($this) {
            self::Pass => "\033[32m",  // green
            self::Warn => "\033[33m",  // yellow
            self::Fail => "\033[31m",  // red
            self::Skip => "\033[90m",  // grey
        };
    }
}

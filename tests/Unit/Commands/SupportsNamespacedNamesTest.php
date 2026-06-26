<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Commands\Command;

final class SupportsNamespacedNamesTest extends TestCase
{
    public function test_double_colon_namespace_separator_is_accepted(): void
    {
        $command = new class extends Command
        {
            protected $signature = 'laranail::package-tools.demo {--flag}';

            public function handle(): int
            {
                return self::SUCCESS;
            }
        };

        $this->assertSame('laranail::package-tools.demo', $command->getName());
    }

    public function test_single_colon_name_is_still_accepted(): void
    {
        $command = new class extends Command
        {
            protected $signature = 'laranail:demo';

            public function handle(): int
            {
                return self::SUCCESS;
            }
        };

        $this->assertSame('laranail:demo', $command->getName());
    }

    public function test_double_colon_aliases_are_accepted(): void
    {
        $command = new class extends Command
        {
            protected $signature = 'laranail::package-tools.demo';

            public function handle(): int
            {
                return self::SUCCESS;
            }
        };

        $command->setAliases(['laranail::package-tools.alias']);

        $this->assertContains('laranail::package-tools.alias', $command->getAliases());
    }
}

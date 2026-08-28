<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Enums;

use DateTimeZone;
use ReflectionEnum;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Enums\Timezone;

/**
 * the generated enum must mirror php's tzdata exactly, in both directions:
 * every case is a valid iana identifier, and every identifier php knows has
 * a case. either direction failing means the enum needs regenerating with
 * tools/generate-timezone-enum.php.
 */
final class TimezoneEnumTest extends TestCase
{
    #[Test]
    public function every_case_value_is_a_valid_timezone_identifier(): void
    {
        $identifiers = DateTimeZone::listIdentifiers();

        foreach (Timezone::cases() as $case) {
            $this->assertContains(
                $case->value,
                $identifiers,
                sprintf('Timezone::%s carries "%s", which php no longer recognises', $case->name, $case->value),
            );
        }
    }

    #[Test]
    public function every_identifier_php_knows_has_an_enum_case(): void
    {
        $values = array_column(Timezone::cases(), 'value');

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $this->assertContains(
                $identifier,
                $values,
                sprintf('php knows "%s" but the enum has no case for it — tzdata moved ahead; regenerate', $identifier),
            );
        }
    }

    #[Test]
    public function enum_and_tzdata_are_the_same_size(): void
    {
        $this->assertCount(count(DateTimeZone::listIdentifiers()), Timezone::cases());
    }

    #[Test]
    public function it_has_a_utc_case(): void
    {
        $this->assertSame('UTC', Timezone::Utc->value);
    }

    #[Test]
    public function to_date_time_zone_returns_a_matching_date_time_zone(): void
    {
        $zone = Timezone::Utc->toDateTimeZone();

        $this->assertInstanceOf(DateTimeZone::class, $zone);
        $this->assertSame('UTC', $zone->getName());

        $this->assertSame('Africa/Nairobi', Timezone::AfricaNairobi->toDateTimeZone()->getName());
    }

    #[Test]
    public function the_file_on_disk_is_still_what_the_generator_produces(): void
    {
        // The parity tests above answer whether the enum's *content* matches
        // tzdata, which is what matters for correctness. They cannot see a hand
        // edit that leaves the case list alone — an added method, a reformatted
        // docblock — and that is what turns the next regeneration into a diff
        // nobody expected and someone reverts by accident.
        $generator = dirname(__DIR__, 3) . '/tools/generate-timezone-enum.php';

        $output = [];
        $exitCode = 0;
        exec(sprintf('%s %s --check 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($generator)), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    #[Test]
    public function it_points_at_chronos_replacement(): void
    {
        // chrono ships an UPGRADING entry and a migration recipe for this move;
        // for a while this copy said nothing, so the only people who learned it
        // had moved were the ones already reading chrono.
        $doc = (new ReflectionEnum(Timezone::class))->getDocComment();

        $this->assertIsString($doc);
        $this->assertStringContainsString('@deprecated', $doc);
        $this->assertStringContainsString('Chrono\\Core\\Enums\\Timezone', $doc);
    }
}

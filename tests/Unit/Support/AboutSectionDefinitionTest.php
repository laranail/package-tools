<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Support;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;
use Stringable;

final class AboutSectionDefinitionTest extends TestCase
{
    public function test_scalar_fields_resolve_as_strings(): void
    {
        $section = AboutSectionDefinition::make('Demo')
            ->field('Version', '1.2.3')
            ->field('Count', 42)
            ->field('Ratio', 1.5)
            ->field('Enabled', true)
            ->field('Disabled', false);

        $this->assertSame(
            ['Version' => '1.2.3', 'Count' => '42', 'Ratio' => '1.5', 'Enabled' => 'true', 'Disabled' => 'false'],
            $section->resolve(),
        );
    }

    public function test_field_accepts_every_data_type(): void
    {
        $section = AboutSectionDefinition::make('Types')
            ->field('Null', null)
            ->field('BackedEnum', SeederExecutionMode::Queued)          // → backing value
            ->field('PureEnum', AboutPureEnumFixture::Beta)             // → case name
            ->field('Date', new DateTimeImmutable('2026-07-09T08:30:00+00:00'))
            ->field('Array', ['a' => 1, 'b' => 2])                      // → compact JSON
            ->field('Arrayable', new AboutArrayableFixture(['x' => 'y']))
            ->field('Stringable', new AboutStringableFixture('str'))
            ->field('ObjectWithToString', new class
            {
                public function __toString(): string
                {
                    return 'obj';
                }
            });

        $this->assertSame([
            'Null' => 'null',
            'BackedEnum' => 'queued',
            'PureEnum' => 'Beta',
            'Date' => '2026-07-09T08:30:00+00:00',
            'Array' => '{"a":1,"b":2}',
            'Arrayable' => '{"x":"y"}',
            'Stringable' => 'str',
            'ObjectWithToString' => 'obj',
        ], $section->resolve());
    }

    public function test_closures_may_return_any_data_type(): void
    {
        $section = AboutSectionDefinition::make('Lazy types')
            ->field('Enum', fn (): SeederExecutionMode => SeederExecutionMode::Inline)
            ->field('List', fn (): array => [1, 2, 3])
            ->field('Nullable', fn (): null => null);

        $this->assertSame(
            ['Enum' => 'inline', 'List' => '[1,2,3]', 'Nullable' => 'null'],
            $section->resolve(),
        );
    }

    public function test_closure_fields_are_lazy_and_resolve_per_field(): void
    {
        $evaluated = false;

        $section = AboutSectionDefinition::make('Demo')
            ->field('Lazy', function () use (&$evaluated): string {
                $evaluated = true;

                return 'value';
            });

        $this->assertFalse($evaluated); // nothing runs at declaration time
        $this->assertSame(['Lazy' => 'value'], $section->resolve());
        $this->assertTrue($evaluated);
    }

    public function test_bulk_sources_merge_before_explicit_fields(): void
    {
        $section = AboutSectionDefinition::make('Demo')
            ->fieldsUsing(fn (): array => ['A' => 'bulk', 'B' => 'bulk'])
            ->field('B', 'explicit'); // explicit field wins on collision

        $this->assertSame(['A' => 'bulk', 'B' => 'explicit'], $section->resolve());
    }

    public function test_fields_batch_registration(): void
    {
        $section = AboutSectionDefinition::make('Demo')->fields([
            'One' => '1',
            'Two' => fn (): string => '2',
        ]);

        $this->assertSame(['One' => '1', 'Two' => '2'], $section->resolve());
    }

    public function test_fields_accepts_numeric_string_keys(): void
    {
        // php turns '2026' into an int key; fields() must cast it back
        // instead of tripping the string type on field()
        $section = AboutSectionDefinition::make('Demo')->fields(['2026' => 'planned']);

        $this->assertSame(['2026' => 'planned'], $section->resolve());
    }

    public function test_config_gates_control_display(): void
    {
        config()->set('about.on', true);
        config()->set('about.off', false);
        config()->set('about.set', 0);

        $this->assertTrue(AboutSectionDefinition::make('x')->whenConfig('about.on')->shouldDisplay());
        $this->assertFalse(AboutSectionDefinition::make('x')->whenConfig('about.off')->shouldDisplay());
        $this->assertTrue(AboutSectionDefinition::make('x')->whenConfigNotNull('about.set')->shouldDisplay());
        $this->assertFalse(AboutSectionDefinition::make('x')->whenConfigNotNull('about.missing')->shouldDisplay());
        $this->assertTrue(AboutSectionDefinition::make('x')->shouldDisplay()); // no gate
    }

    public function test_to_array_masks_closures(): void
    {
        $section = AboutSectionDefinition::make('Demo')
            ->field('Static', 'v')
            ->field('Lazy', fn (): string => 'x')
            ->fieldsUsing(fn (): array => [])
            ->whenConfig('about.on');

        $array = $section->toArray();

        $this->assertSame('Demo', $array['label']);
        $this->assertSame(['Static' => 'v', 'Lazy' => 'closure'], $array['fields']);
        $this->assertSame(1, $array['bulk_sources']);
        $this->assertSame('about.on', $array['gate']['key']);
        $this->assertJson($section->toJson());
    }

    public function test_package_accepts_definitions_and_rejects_bare_labels(): void
    {
        $package = new Package;
        $package->setName('acme/demo');

        $package->hasAboutSection(AboutSectionDefinition::make('Fluent')->field('K', 'v'));
        $package->hasAboutSection('Legacy', fn (): array => ['L' => 'w']);
        $package->hasAboutSections([
            AboutSectionDefinition::make('Batch'),
            'Pairs' => fn (): array => [],
        ]);

        $this->assertCount(2, $package->aboutSectionDefinitions);
        $this->assertCount(2, $package->aboutSections);

        $this->expectException(InvalidArgumentException::class);
        $package->hasAboutSection('NoData');
    }

    public function test_a_throwing_field_renders_the_fallback_not_a_crash(): void
    {
        $section = AboutSectionDefinition::make('Demo')
            ->field('Ok', 'fine')
            ->field('Boom', fn (): string => throw new RuntimeException('db not migrated'))
            ->field('AlsoOk', fn (): string => 'still here');

        // The throwing field is placeholdered; the section still renders whole.
        $this->assertSame(
            ['Ok' => 'fine', 'Boom' => 'n/a', 'AlsoOk' => 'still here'],
            $section->resolve(),
        );
    }

    public function test_custom_fallback_message(): void
    {
        $section = AboutSectionDefinition::make('Demo')
            ->fallback('n/a (not migrated)')
            ->field('Users', fn (): string => throw new RuntimeException('no table'));

        $this->assertSame(['Users' => 'n/a (not migrated)'], $section->resolve());
    }

    public function test_a_throwing_bulk_source_is_skipped_without_crashing(): void
    {
        $section = AboutSectionDefinition::make('Demo')
            ->fieldsUsing(fn (): array => throw new RuntimeException('boom'))
            ->fieldsUsing(fn (): array => ['Survivor' => 'yes'])
            ->field('Explicit', 'kept');

        $this->assertSame(
            ['Survivor' => 'yes', 'Explicit' => 'kept'],
            $section->resolve(),
        );
    }
}

enum AboutPureEnumFixture
{
    case Alpha;
    case Beta;
}

final readonly class AboutArrayableFixture implements Arrayable
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}

final readonly class AboutStringableFixture implements Stringable
{
    public function __construct(private string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }
}

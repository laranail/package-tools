<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Simtabi\Laranail\Package\Tools\Attributes\AsArtisanCommand;
use Simtabi\Laranail\Package\Tools\Attributes\AsFacade;
use Simtabi\Laranail\Package\Tools\Attributes\AsRoute;
use Simtabi\Laranail\Package\Tools\Attributes\AsViewComposer;

#[AsArtisanCommand(signature: 'foo:run', description: 'Run the foo task')]
final class CommandFixture {}

#[AsRoute(method: 'GET', uri: '/foo')]
#[AsRoute(method: 'POST', uri: '/foo', name: 'foo.create', middleware: ['web'])]
final class RouteFixture {}

#[AsFacade(alias: 'Foo', accessor: 'foo.contract')]
interface FacadeFixture {}

#[AsViewComposer(views: ['layouts.app', 'partials.header'])]
final class ViewComposerFixture {}

#[AsViewComposer(views: 'layouts.app')]
final class SingleViewComposerFixture {}

final class AttributesTest extends TestCase
{
    public function test_as_artisan_command_carries_signature_and_description(): void
    {
        $attr = (new ReflectionClass(CommandFixture::class))
            ->getAttributes(AsArtisanCommand::class)[0]
            ->newInstance();

        $this->assertSame('foo:run', $attr->signature);
        $this->assertSame('Run the foo task', $attr->description);
    }

    public function test_as_route_is_repeatable(): void
    {
        $attrs = (new ReflectionClass(RouteFixture::class))
            ->getAttributes(AsRoute::class);

        $this->assertCount(2, $attrs);

        $get = $attrs[0]->newInstance();
        $post = $attrs[1]->newInstance();

        $this->assertSame('GET', $get->method);
        $this->assertSame('/foo', $get->uri);
        $this->assertNull($get->name);
        $this->assertSame([], $get->middleware);

        $this->assertSame('POST', $post->method);
        $this->assertSame('foo.create', $post->name);
        $this->assertSame(['web'], $post->middleware);
    }

    public function test_as_facade_carries_alias_and_accessor(): void
    {
        $attr = (new ReflectionClass(FacadeFixture::class))
            ->getAttributes(AsFacade::class)[0]
            ->newInstance();

        $this->assertSame('Foo', $attr->alias);
        $this->assertSame('foo.contract', $attr->accessor);
    }

    public function test_as_view_composer_accepts_array_or_string(): void
    {
        $multi = (new ReflectionClass(ViewComposerFixture::class))
            ->getAttributes(AsViewComposer::class)[0]
            ->newInstance();
        $single = (new ReflectionClass(SingleViewComposerFixture::class))
            ->getAttributes(AsViewComposer::class)[0]
            ->newInstance();

        $this->assertSame(['layouts.app', 'partials.header'], $multi->views);
        $this->assertSame('layouts.app', $single->views);
    }

    public function test_as_artisan_command_is_readonly(): void
    {
        $rc = new ReflectionClass(AsArtisanCommand::class);

        $this->assertTrue($rc->isFinal());
        $this->assertTrue($rc->isReadOnly());
    }
}

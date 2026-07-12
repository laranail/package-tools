<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Utility;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Utility\NamespaceResolver;

final class NamespaceResolverTest extends TestCase
{
    private NamespaceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new NamespaceResolver;
    }

    public function test_normalize_trims_slashes_and_unifies_separators(): void
    {
        $this->assertSame('Modules/Admin', $this->resolver->normalize('\\Modules\\Admin\\'));
        $this->assertSame('Modules/Admin', $this->resolver->normalize('/Modules/Admin/'));
    }

    public function test_resolve_delegates_to_normalize(): void
    {
        $this->assertSame('Foo/Bar', $this->resolver->resolve('\\Foo\\Bar'));
    }

    public function test_validate_rejects_empty_and_zero(): void
    {
        $this->assertFalse($this->resolver->validate(''));
        $this->assertFalse($this->resolver->validate('0'));
    }

    public function test_validate_accepts_valid_characters(): void
    {
        $this->assertTrue($this->resolver->validate('Modules\\Admin'));
        $this->assertTrue($this->resolver->validate('modules/admin-panel_v2'));
    }

    public function test_validate_rejects_invalid_characters(): void
    {
        $this->assertFalse($this->resolver->validate('Foo Bar'));
        $this->assertFalse($this->resolver->validate('Foo@Bar'));
    }

    public function test_to_dashed_converts_dots_and_backslashes(): void
    {
        $this->assertSame('modules/admin', $this->resolver->toDashed('modules.admin'));
        $this->assertSame('Modules/Admin', $this->resolver->toDashed('Modules\\Admin'));
    }

    public function test_to_dotted_converts_slashes_and_backslashes(): void
    {
        $this->assertSame('modules.admin', $this->resolver->toDotted('modules/admin'));
        $this->assertSame('Modules.Admin', $this->resolver->toDotted('Modules\\Admin'));
    }

    public function test_to_psr4_capitalises_each_segment(): void
    {
        $this->assertSame('Modules\\Admin', $this->resolver->toPsr4('modules/admin'));
        $this->assertSame('Modules\\Admin', $this->resolver->toPsr4('\\modules\\admin\\'));
    }

    public function test_can_resolve_matches_validate(): void
    {
        $this->assertTrue($this->resolver->canResolve('Modules/Admin'));
        $this->assertFalse($this->resolver->canResolve(''));
        $this->assertFalse($this->resolver->canResolve('bad space'));
    }
}

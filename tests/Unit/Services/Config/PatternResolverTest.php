<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Services\Config;

use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigService;
use Simtabi\Laranail\Package\Tools\Services\Config\PatternResolver;

final class PatternResolverTest extends TestCase
{
    private PatternResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $app = $this->createMock(Application::class);
        $this->resolver = new PatternResolver(new ConfigService($app));
    }

    public function test_resolve_replaces_simple_placeholders(): void
    {
        $result = $this->resolver->resolve('{vendor}/{name}', [
            'vendor' => 'acme',
            'name' => 'widgets',
        ]);

        $this->assertSame('acme/widgets', $result);
    }

    public function test_resolve_auto_derives_kebab_and_snake_from_module(): void
    {
        $result = $this->resolver->resolve('{module_kebab}|{module_snake}', [
            'module' => 'BlogPosts',
        ]);

        $this->assertSame('blog-posts|blog_posts', $result);
    }

    public function test_resolve_does_not_override_explicit_kebab_or_snake(): void
    {
        $result = $this->resolver->resolve('{module_kebab}', [
            'module' => 'BlogPosts',
            'module_kebab' => 'custom-value',
        ]);

        $this->assertSame('custom-value', $result);
    }

    public function test_resolve_casts_non_string_values_to_string(): void
    {
        $result = $this->resolver->resolve('v{version}', ['version' => 2]);

        $this->assertSame('v2', $result);
    }

    public function test_resolve_leaves_unknown_placeholders_untouched(): void
    {
        $result = $this->resolver->resolve('{vendor}/{unknown}', ['vendor' => 'acme']);

        $this->assertSame('acme/{unknown}', $result);
    }

    public function test_get_available_variables_lists_known_tokens(): void
    {
        $variables = $this->resolver->getAvailableVariables();

        $this->assertContains('vendor', $variables);
        $this->assertContains('module', $variables);
        $this->assertContains('component_ns', $variables);
    }

    public function test_validate_pattern_accepts_known_variables(): void
    {
        $this->assertTrue($this->resolver->validatePattern('{vendor}/{module}/{name}'));
    }

    public function test_validate_pattern_rejects_unknown_variables(): void
    {
        $this->assertFalse($this->resolver->validatePattern('{vendor}/{bogus}'));
    }

    public function test_validate_pattern_accepts_literal_without_variables(): void
    {
        $this->assertTrue($this->resolver->validatePattern('static/path'));
    }

    public function test_extract_variables_returns_each_placeholder(): void
    {
        $this->assertSame(
            ['vendor', 'module', 'name'],
            $this->resolver->extractVariables('{vendor}/{module}/{name}'),
        );
    }

    public function test_extract_variables_returns_empty_for_literal(): void
    {
        $this->assertSame([], $this->resolver->extractVariables('no/placeholders'));
    }

    public function test_has_variable_detects_presence(): void
    {
        $this->assertTrue($this->resolver->hasVariable('{vendor}/lib', 'vendor'));
        $this->assertFalse($this->resolver->hasVariable('{vendor}/lib', 'module'));
    }

    public function test_can_resolve_true_when_a_known_variable_is_present(): void
    {
        $this->assertTrue($this->resolver->canResolve('{vendor}/{bogus}'));
    }

    public function test_can_resolve_false_when_only_unknown_variables(): void
    {
        $this->assertFalse($this->resolver->canResolve('{bogus}'));
    }

    public function test_can_resolve_false_when_no_variables_at_all(): void
    {
        $this->assertFalse($this->resolver->canResolve('static/path'));
    }
}

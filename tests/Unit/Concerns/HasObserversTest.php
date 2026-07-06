<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Package\Tools\Concerns\Package\HasObservers;
use Simtabi\Laranail\Package\Tools\Tests\TestCase;

/**
 * declarative observer registration: string and array shapes, per-model
 * accumulation, and fluency.
 */
final class HasObserversTest extends TestCase
{
    use HasObservers;

    #[Test]
    public function it_registers_a_single_observer_string(): void
    {
        $this->registerObserver('Acme\\Blog\\Models\\Post', 'Acme\\Blog\\Observers\\PostObserver');

        $this->assertSame(
            ['Acme\\Blog\\Models\\Post' => ['Acme\\Blog\\Observers\\PostObserver']],
            $this->getObservers(),
        );
    }

    #[Test]
    public function it_registers_an_observer_list_for_one_model(): void
    {
        $this->registerObserver('Acme\\Post', ['Acme\\AuditObserver', 'Acme\\CacheObserver']);

        $this->assertSame(
            ['Acme\\Post' => ['Acme\\AuditObserver', 'Acme\\CacheObserver']],
            $this->getObservers(),
        );
    }

    #[Test]
    public function observers_accumulate_per_model_across_calls(): void
    {
        $this->registerObserver('Acme\\Post', 'Acme\\AuditObserver');
        $this->registerObserver('Acme\\Post', 'Acme\\CacheObserver');

        $this->assertSame(
            ['Acme\\Post' => ['Acme\\AuditObserver', 'Acme\\CacheObserver']],
            $this->getObservers(),
        );
    }

    #[Test]
    public function batch_registration_accepts_mixed_string_and_array_values(): void
    {
        $this->registerObservers([
            'Acme\\Post' => 'Acme\\PostObserver',
            'Acme\\Comment' => ['Acme\\CommentObserver', 'Acme\\SpamObserver'],
        ]);

        $this->assertSame([
            'Acme\\Post' => ['Acme\\PostObserver'],
            'Acme\\Comment' => ['Acme\\CommentObserver', 'Acme\\SpamObserver'],
        ], $this->getObservers());
    }

    #[Test]
    public function it_returns_an_empty_array_when_nothing_is_registered(): void
    {
        $this->assertSame([], $this->getObservers());
    }

    #[Test]
    public function registration_is_fluent(): void
    {
        $result = $this->registerObserver('Acme\\Post', 'Acme\\PostObserver')
            ->registerObservers(['Acme\\Comment' => 'Acme\\CommentObserver']);

        $this->assertSame($this, $result);
        $this->assertCount(2, $this->getObservers());
    }
}

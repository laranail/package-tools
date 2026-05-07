<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Attributes;

use Attribute;

/**
 * Marks a class as a discoverable view composer.
 *
 * Use with `Package::discoversWithAttributes()` (ADR-009). The Package
 * builder will register the class as a Laravel view composer for the
 * specified view(s).
 *
 * Example:
 * ```
 * #[AsViewComposer(views: ['layouts.app', 'partials.header'])]
 * final class AppViewComposer { public function compose(View $view) { … } }
 *
 * // Single view also accepted:
 * #[AsViewComposer(views: 'layouts.app')]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsViewComposer
{
    public function __construct(
        /** @var string|string[] */
        public string|array $views,
    ) {}
}

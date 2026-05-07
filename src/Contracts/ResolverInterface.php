<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Contracts;

/**
 * Resolver Interface
 *
 * Contract for resolver services that transform or resolve values
 * (paths, namespaces, placeholders, etc.)
 */
interface ResolverInterface
{
    /**
     * Resolve an input value
     *
     * @param string $input Value to resolve
     * @return string Resolved value
     */
    public function resolve(string $input): string;

    /**
     * Check if an input can be resolved
     *
     * @param string $input Value to check
     */
    public function canResolve(string $input): bool;
}

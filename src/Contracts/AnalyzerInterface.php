<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Contracts;

/**
 * Analyzer Interface
 *
 * Contract for analyzer services that analyze code, configurations, or structures
 */
interface AnalyzerInterface
{
    /**
     * Analyze a target and return findings
     *
     * @param string $target Target to analyze (path, code, etc.)
     * @return array<string, mixed> Analysis results
     */
    public function analyze(string $target): array;

    /**
     * Get analysis report in specified format
     *
     * @param array<string, mixed> $findings Analysis findings
     * @param string $format Report format (json, html, text)
     * @return string Formatted report
     */
    public function getReport(array $findings, string $format = 'json'): string;
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Dist;

/**
 * Reads a revision by shelling out to git.
 */
final readonly class GitRevisionReader implements RevisionReader
{
    public function __construct(private string $root) {}

    public function manifest(string $revision): string
    {
        [$status, $out] = $this->run(sprintf('git show %s:composer.json', escapeshellarg($revision)));

        if ($status !== 0 || trim($out) === '') {
            throw CouldNotReadRevision::manifest($revision);
        }

        return $out;
    }

    public function archivedPaths(string $revision): array
    {
        [$status, $out] = $this->run(
            sprintf('git archive --format=tar %s | tar -t', escapeshellarg($revision)),
        );

        if ($status !== 0) {
            throw CouldNotReadRevision::archive($revision);
        }

        return $this->lines($out);
    }

    public function trackedPaths(string $revision): array
    {
        [, $out] = $this->run(sprintf('git ls-tree -r --name-only %s', escapeshellarg($revision)));

        return $this->lines($out);
    }

    /**
     * @return list<string>
     */
    private function lines(string $output): array
    {
        $lines = array_map(
            static fn (string $line): string => rtrim(trim($line), '/'),
            explode("\n", $output),
        );

        return array_values(array_filter($lines, static fn (string $l): bool => $l !== ''));
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function run(string $command): array
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
        );

        if (! is_resource($process)) {
            return [1, ''];
        }

        $out = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out];
    }
}

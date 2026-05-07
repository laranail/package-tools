<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Services\Environment;

use LogicException;
use RuntimeException;
use Simtabi\Laranail\PackageTools\Services\Environment\Events\EnvFileMutated;
use Simtabi\Laranail\PackageTools\Services\Environment\Exceptions\EnvFileNotFound;
use Simtabi\Laranail\PackageTools\Services\Environment\Exceptions\EnvFileNotReadable;
use Simtabi\Laranail\PackageTools\Services\Environment\Exceptions\EnvFileNotWritable;
use Throwable;

/**
 * Append-only writer for the host Laravel app's .env (ADR-006).
 *
 * Discovery order:
 *   1. Laravel's `Application::environmentFilePath()` if running inside an app.
 *   2. Walk up from the working directory until a `.env` is found.
 *   3. Otherwise: missing — every read returns null, every write throws.
 *
 * Write contract:
 *   - appendIfMissing(key, value, comment) → no-op if key exists.
 *   - appendBlock(entries, sectionTitle)   → adds a labelled block at EOF.
 *   - forceSet(key, value, *, true)        → ONLY destructive method;
 *     requires acknowledgeDestructive=true. Emits EnvFileMutated.
 *
 * Atomicity:
 *   1. Backup → .env.bak.<timestamp>.
 *   2. Write tmp → .env.tmp.<pid>.
 *   3. rename(tmp, env) — atomic on POSIX.
 *   4. Dispatch EnvFileMutated event (if a dispatcher is wired).
 *
 * Newline handling: detects LF vs CRLF in the existing file and matches
 * it. Detects whether the file ends with a newline; prepends one to the
 * appended block when missing so the last existing line never fuses with
 * a new key.
 *
 * No external dependencies: works inside or outside a Laravel context.
 * If the optional `event-dispatcher` callable is not set, events are
 * discarded silently.
 */
final class EnvFileService
{
    private readonly EnvFileParser $parser;

    /** @var (callable(EnvFileMutated): void)|null */
    private $eventDispatcher;

    public function __construct(
        private readonly string $path,
        ?EnvFileParser $parser = null,
        ?callable $eventDispatcher = null,
    ) {
        $this->parser = $parser ?? new EnvFileParser;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function discover(?callable $eventDispatcher = null): self
    {
        $path = self::resolvePath();

        return new self($path, eventDispatcher: $eventDispatcher);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function isReadable(): bool
    {
        return $this->exists() && is_readable($this->path);
    }

    public function isWritable(): bool
    {
        return $this->exists() && is_writable($this->path);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $this->guardReadable();

        return $this->parser->parse((string) file_get_contents($this->path));
    }

    public function read(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Append KEY=value if (and only if) KEY does not already exist.
     *
     * @return bool true when the file was mutated; false when key was already present.
     */
    public function appendIfMissing(string $key, string $value, ?string $comment = null): bool
    {
        $this->guardWritable();

        if ($this->has($key)) {
            return false;
        }

        $existing = (string) file_get_contents($this->path);
        $newline = $this->detectNewline($existing);
        $needsLeadingNewline = $existing !== '' && ! str_ends_with($existing, $newline);

        $block = '';
        if ($needsLeadingNewline) {
            $block .= $newline;
        } else {
            // separator line so appended block is visually distinct
            $block .= $newline;
        }
        if ($comment !== null) {
            $block .= '# ' . $comment . $newline;
        }
        $block .= sprintf('%s=%s%s', $key, $this->quoteValue($value), $newline);

        $backup = $this->backup();
        $this->atomicAppend($block);

        $this->dispatch(new EnvFileMutated(
            path: $this->path,
            addedKeys: [$key],
            backupPath: $backup,
            action: 'appendIfMissing',
        ));

        return true;
    }

    /**
     * Append a labelled block of entries; skips keys that already exist.
     *
     * @param array<string, string> $entries
     * @return int count of keys actually appended.
     */
    public function appendBlock(array $entries, ?string $sectionTitle = null): int
    {
        $this->guardWritable();

        $present = $this->all();
        $toAppend = array_filter(
            $entries,
            static fn (string $key): bool => $key !== '',
            ARRAY_FILTER_USE_KEY,
        );
        $missing = array_diff_key($toAppend, $present);

        if ($missing === []) {
            return 0;
        }

        $existing = (string) file_get_contents($this->path);
        $newline = $this->detectNewline($existing);
        $needsLeadingNewline = $existing !== '' && ! str_ends_with($existing, $newline);

        $block = '';
        if ($needsLeadingNewline) {
            $block .= $newline;
        }
        $block .= $newline; // visual separator
        if ($sectionTitle !== null) {
            $block .= '# === ' . $sectionTitle . ' ===' . $newline;
        }
        foreach ($missing as $key => $value) {
            $block .= sprintf('%s=%s%s', $key, $this->quoteValue((string) $value), $newline);
        }

        $backup = $this->backup();
        $this->atomicAppend($block);

        $this->dispatch(new EnvFileMutated(
            path: $this->path,
            addedKeys: array_keys($missing),
            backupPath: $backup,
            action: 'appendBlock',
        ));

        return count($missing);
    }

    /**
     * Destructive — overwrites or appends a key.
     * Gated by an explicit acknowledgeDestructive flag so accidental
     * loss-of-data calls fail loudly. Emits EnvFileMutated.
     */
    public function forceSet(
        string $key,
        string $value,
        bool $acknowledgeDestructive,
    ): bool {
        if (! $acknowledgeDestructive) {
            throw new LogicException(
                'EnvFileService::forceSet requires $acknowledgeDestructive=true. ' .
                'Use appendIfMissing() / appendBlock() for non-destructive writes.',
            );
        }

        $this->guardWritable();

        $existing = (string) file_get_contents($this->path);
        $newline = $this->detectNewline($existing);

        // Replace existing KEY= line if present, otherwise append.
        $replaced = preg_replace(
            '/^' . preg_quote($key, '/') . '=.*$/m',
            $key . '=' . $this->quoteValue($value),
            $existing,
            1,
            $count,
        );

        if ($replaced === null) {
            throw new RuntimeException("EnvFileService::forceSet preg_replace error for key: {$key}");
        }

        if ($count === 0) {
            // Append (use the same logic as appendIfMissing for newline safety).
            $needsLeadingNewline = $existing !== '' && ! str_ends_with($existing, $newline);
            $replaced = $existing
                . ($needsLeadingNewline ? $newline : '')
                . sprintf('%s=%s%s', $key, $this->quoteValue($value), $newline);
        }

        $backup = $this->backup();
        $this->atomicWrite($replaced);

        $this->dispatch(new EnvFileMutated(
            path: $this->path,
            addedKeys: [$key],
            backupPath: $backup,
            action: 'forceSet',
        ));

        return true;
    }

    /**
     * Make a timestamped backup of the .env. Returns the absolute backup path.
     */
    public function backup(): string
    {
        $this->guardReadable();

        $stamp = date('Ymd-His');
        $backupPath = $this->path . '.bak.' . $stamp;
        // Avoid clobber if multiple writes happen within the same second.
        $i = 1;
        while (file_exists($backupPath)) {
            $backupPath = $this->path . '.bak.' . $stamp . '-' . $i;
            $i++;
        }

        if (! @copy($this->path, $backupPath)) {
            throw new RuntimeException("EnvFileService: backup failed for {$this->path}");
        }

        return $backupPath;
    }

    private function guardReadable(): void
    {
        if (! $this->exists()) {
            throw EnvFileNotFound::at($this->path);
        }
        if (! is_readable($this->path)) {
            throw EnvFileNotReadable::at($this->path);
        }
    }

    private function guardWritable(): void
    {
        $this->guardReadable();
        if (! is_writable($this->path)) {
            throw EnvFileNotWritable::at($this->path);
        }
    }

    private function detectNewline(string $contents): string
    {
        return str_contains($contents, "\r\n") ? "\r\n" : "\n";
    }

    /**
     * Quote a value if it contains characters that would break parsing
     * (whitespace, #, =) or carries control characters.
     */
    private function quoteValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\s#"\'\\\\=]/', $value) === 1) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }

    private function atomicAppend(string $block): void
    {
        $existing = (string) file_get_contents($this->path);
        $this->atomicWrite($existing . $block);
    }

    /**
     * tmp + rename: write to a sibling tmp file, then atomically rename
     * onto the target. On POSIX, rename() within the same filesystem is
     * atomic (no half-written .env if power dies mid-write).
     */
    private function atomicWrite(string $contents): void
    {
        $tmp = $this->path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(2));
        if (file_put_contents($tmp, $contents) === false) {
            @unlink($tmp);
            throw new RuntimeException("EnvFileService: tmp write failed at {$tmp}");
        }
        // Match original file mode if possible.
        $perms = @fileperms($this->path);
        if ($perms !== false) {
            @chmod($tmp, $perms & 0o777);
        }
        if (! @rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new RuntimeException("EnvFileService: atomic rename failed for {$this->path}");
        }
    }

    private function dispatch(EnvFileMutated $event): void
    {
        if ($this->eventDispatcher !== null) {
            ($this->eventDispatcher)($event);
        }
    }

    /**
     * Discovery: Laravel `Application::environmentFilePath()` first, then
     * walk-up fallback. Throws EnvFileNotFound if no .env exists anywhere.
     */
    private static function resolvePath(): string
    {
        if (function_exists('app')) {
            try {
                $app = app();
                if (is_object($app) && method_exists($app, 'environmentFilePath')) {
                    $candidate = $app->environmentFilePath();
                    if (is_string($candidate) && is_file($candidate)) {
                        return $candidate;
                    }
                }
            } catch (Throwable) {
                // Fall through to walk-up.
            }
        }

        $dir = getcwd();
        if ($dir === false) {
            throw EnvFileNotFound::at('(no working directory)');
        }
        while ($dir !== '/' && $dir !== '') {
            $candidate = $dir . DIRECTORY_SEPARATOR . '.env';
            if (is_file($candidate)) {
                return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw EnvFileNotFound::at(getcwd() . '/.env (walked up to /, no .env found)');
    }
}

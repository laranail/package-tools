<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use PhpToken;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\Facade;

/**
 * Finds facade and service-locating helper calls in PHP source: the scanner behind
 * {@see AssertsNoFacadeCalls}.
 *
 * A package's own classes take Laravel's contracts in their constructors. A facade or a helper
 * such as `app()` or `config()` called from inside a method is a hidden dependency: the class
 * cannot be built or tested without a booted container, and nothing in its signature says so.
 *
 * Reported:
 *  - `facade`: a static call on a class under `Illuminate\Support\Facades\`, however it is
 *    imported or aliased, or on a global facade alias such as `\Cache` from a namespaced file.
 *  - `helper`: a call to one of {@see HELPERS}.
 *
 * Not reported, because they are the composition root or the sugar itself: any file under a
 * `Providers/` directory, and any class that extends `Facade`. `now()` is a seam tests control
 * with `travelTo()`, and the rendering helpers (`view()`, `response()`, `route()`, `__()`) belong
 * to controllers; neither is on the list.
 */
final class FacadeCalls
{
    /**
     * Mark a line (or the line above it) holding a deliberate call, with a reason:
     * `// @facade-call-exempt: bootstraps before the container exists`.
     */
    public const string EXEMPTION = '@facade-call-exempt';

    /** Global helpers that locate a service rather than receive it. */
    public const array HELPERS = [
        'app', 'resolve', 'config', 'cache', 'request', 'session', 'auth',
        'event', 'logger', 'info', 'dispatch', 'broadcast', 'cookie',
    ];

    private const string FACADE_NAMESPACE = 'Illuminate\\Support\\Facades\\';

    /**
     * Every facade or helper call in $source, as [line, rule, call].
     *
     * @return list<array{int, string, string}>
     */
    public static function in(string $source): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $t): bool => ! $t->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));

        if (self::extendsFacade($tokens)) {
            return [];
        }

        $imports = self::imports($tokens);
        $aliases = array_map('strtolower', array_keys(Facade::defaultAliases()->all()));
        $lines = preg_split('/\R/', $source) ?: [];
        $found = [];

        foreach ($tokens as $i => $token) {
            if (! $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                continue;
            }

            $next = $tokens[$i + 1] ?? null;
            $prev = $tokens[$i - 1] ?? null;

            if ($next?->is(T_DOUBLE_COLON) && ! self::isDeclarationContext($prev)
                && ! ($tokens[$i + 2] ?? null)?->is(T_CLASS)) {   // `Cache::class` names it, calls nothing
                $class = self::resolveClass($token, $imports);
                $isFacade = str_starts_with($class, self::FACADE_NAMESPACE)
                    || ($token->is(T_NAME_FULLY_QUALIFIED) && in_array(strtolower(ltrim($token->text, '\\')), $aliases, true));

                if ($isFacade && ! self::exempt($lines, $token->line)) {
                    $found[] = [$token->line, 'facade', ltrim($token->text, '\\') . '::'];
                }

                continue;
            }

            if ($next?->text === '(' && ! self::isMemberOrDeclaration($prev)) {
                $name = strtolower(ltrim($token->text, '\\'));

                if ($token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) && in_array($name, self::HELPERS, true)
                    && ! self::exempt($lines, $token->line)) {
                    $found[] = [$token->line, 'helper', $name . '()'];
                }
            }
        }

        return $found;
    }

    /**
     * Scan every PHP file under $dirs, skipping `Providers/` directories and $exemptFiles.
     *
     * @param list<string> $dirs
     * @param list<string> $exemptFiles basenames
     *
     * @return array{int, list<string>} [files scanned, "path:line rule call" offenders]
     */
    public static function scan(array $dirs, array $exemptFiles = []): array
    {
        $scanned = 0;
        $offenders = [];

        foreach ($dirs as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                $path = $file->getPathname();

                if ($file->getExtension() !== 'php'
                    || in_array($file->getBasename(), $exemptFiles, true)
                    || preg_match('#[/\\\\]Providers[/\\\\]#', $path) === 1) {
                    continue;
                }

                $scanned++;

                foreach (self::in((string) file_get_contents($path)) as [$line, $rule, $call]) {
                    $offenders[] = sprintf('%s:%d %s %s', $path, $line, $rule, $call);
                }
            }
        }

        sort($offenders);

        return [$scanned, $offenders];
    }

    /**
     * `use` imports at the top level of the file: lowercase alias => fully qualified name.
     *
     * @param list<PhpToken> $tokens
     *
     * @return array{namespace: string, uses: array<string, string>}
     */
    private static function imports(array $tokens): array
    {
        $namespace = '';
        $uses = [];
        $depth = 0;

        foreach ($tokens as $i => $token) {
            if ($token->text === '{') {
                $depth++;
            } elseif ($token->text === '}') {
                $depth--;
            }

            if ($token->is(T_NAMESPACE) && $depth === 0) {
                $namespace = trim($tokens[$i + 1]->text ?? '', '\\');
            }

            if (! $token->is(T_USE) || $depth !== 0) {
                continue;
            }

            $name = $tokens[$i + 1] ?? null;
            if ($name === null || ! $name->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                continue;   // `use function …`, `use const …`, group imports: not class imports we track
            }

            $fqn = ltrim($name->text, '\\');
            $alias = substr($fqn, (int) strrpos('\\' . $fqn, '\\'));
            if (($tokens[$i + 2] ?? null)?->is(T_AS)) {
                $alias = $tokens[$i + 3]->text ?? $alias;
            }

            $uses[strtolower($alias)] = $fqn;
        }

        return ['namespace' => $namespace, 'uses' => $uses];
    }

    /**
     * @param array{namespace: string, uses: array<string, string>} $imports
     */
    private static function resolveClass(PhpToken $token, array $imports): string
    {
        if ($token->is(T_NAME_FULLY_QUALIFIED)) {
            return ltrim($token->text, '\\');
        }

        $first = strtolower(explode('\\', $token->text)[0]);
        $rest = substr($token->text, strlen($first));

        if (isset($imports['uses'][$first])) {
            return $imports['uses'][$first] . $rest;
        }

        return $imports['namespace'] === '' ? $token->text : $imports['namespace'] . '\\' . $token->text;
    }

    /** @param list<PhpToken> $tokens */
    private static function extendsFacade(array $tokens): bool
    {
        foreach ($tokens as $i => $token) {
            if ($token->is(T_EXTENDS)) {
                $parent = ltrim($tokens[$i + 1]->text ?? '', '\\');
                if ($parent === 'Facade' || $parent === 'Illuminate\\Support\\Facades\\Facade') {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isDeclarationContext(?PhpToken $prev): bool
    {
        return $prev !== null && $prev->is([T_NEW, T_INSTANCEOF]);
    }

    private static function isMemberOrDeclaration(?PhpToken $prev): bool
    {
        return $prev !== null && $prev->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST]);
    }

    /** @param list<string> $lines */
    private static function exempt(array $lines, int $line): bool
    {
        foreach ([$line - 1, $line - 2] as $index) {
            if (isset($lines[$index]) && str_contains($lines[$index], self::EXEMPTION)) {
                return true;
            }
        }

        return false;
    }
}

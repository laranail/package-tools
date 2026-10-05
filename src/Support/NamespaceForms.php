<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use Closure;
use InvalidArgumentException;
use Illuminate\View\FileViewFinder;
use Illuminate\Contracts\View\Factory;
use Illuminate\Translation\Translator;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\Container\Container;

/**
 * Registers the missing spelling of a package's own view and translation namespaces, for a package
 * that registers them by hand rather than through `Package::hasViews()` / `hasTranslations()`.
 *
 * The canonical form is `vendor/package` (it names the composer package, and published overrides
 * nest by vendor); the alias is `vendor-package` (what a Blade tag can spell, and what many
 * packages registered before the slash form was the default). A provider that calls
 * `loadViewsFrom($path, 'laranail-enumerator')` adds the canonical form in one line:
 *
 * ```php
 * $this->loadViewsFrom($views, 'laranail-enumerator');
 * $this->loadTranslationsFrom($lang, 'laranail-enumerator');
 *
 * NamespaceForms::mirror($this->app, 'laranail/enumerator');
 * ```
 *
 * Whichever form is registered, the other is added over the same paths, in both registries; a
 * registry holding neither, or both, is left alone. Call it after the `load*From()` calls: those
 * defer until the view factory and translator are resolved, and so does this, in the same order.
 */
final class NamespaceForms
{
    public static function mirror(Container $app, string $package): void
    {
        if (preg_match('#^[^/\s]+/[^/\s]+$#', $package) !== 1) {
            throw new InvalidArgumentException(
                "NamespaceForms::mirror() needs the composer package name as vendor/package; [{$package}] is not one.",
            );
        }

        $hyphen = str_replace('/', '-', $package);

        self::afterResolving($app, 'view', static function (Factory $view) use ($package, $hyphen): void {
            $finder = $view->getFinder();

            if (! $finder instanceof FileViewFinder) {
                return;
            }

            foreach (self::missing($finder->getHints(), $package, $hyphen) as $form => $paths) {
                $view->addNamespace($form, $paths);
            }
        });

        self::afterResolving($app, 'translator', static function (Translator $translator) use ($package, $hyphen): void {
            /** @var Loader $loader */
            $loader = $translator->getLoader();

            foreach (self::missing($loader->namespaces(), $package, $hyphen) as $form => $path) {
                $translator->addNamespace($form, $path);
            }
        });
    }

    /**
     * The form absent from $registry, keyed to the paths of the form that is present.
     *
     * @template T
     *
     * @param array<string, T> $registry
     *
     * @return array<string, T>
     */
    private static function missing(array $registry, string $slash, string $hyphen): array
    {
        $hasSlash = array_key_exists($slash, $registry);
        $hasHyphen = array_key_exists($hyphen, $registry);

        if ($hasHyphen && ! $hasSlash) {
            return [$slash => $registry[$hyphen]];
        }

        if ($hasSlash && ! $hasHyphen) {
            return [$hyphen => $registry[$slash]];
        }

        return [];
    }

    /**
     * Run $callback once $abstract is resolved -- now, if it already is. The same contract as
     * ServiceProvider::callAfterResolving(), without needing a provider.
     */
    private static function afterResolving(Container $app, string $abstract, Closure $callback): void
    {
        $app->afterResolving($abstract, $callback);

        if ($app->resolved($abstract)) {
            $callback($app->make($abstract), $app);
        }
    }
}

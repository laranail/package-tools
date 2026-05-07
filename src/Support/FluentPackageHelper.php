<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Support;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;

/**
 * FluentPackageHelper - Fluent API for package helper functions
 *
 * Provides a clean, chainable interface for package-specific operations.
 * Inspired by Laravel's cache(), config(), and app() helpers.
 *
 * Usage Examples:
 * - package_blog()->config('posts_per_page', 10)
 * - package_blog()->enabled()
 * - package_blog()->route('index')
 * - package_blog()->asset('logo.png')
 * - package_blog()->view('index', ['posts' => $posts])
 * - package_blog()->trans('messages.welcome')
 * - package_blog('key')  // Shortcut for ->config('key')
 */
class FluentPackageHelper
{
    /**
     * Create a new FluentPackageHelper instance
     *
     * @param string $vendor Vendor name (e.g., 'acme')
     * @param string $package Package name (e.g., 'blog')
     * @param string $configNamespace Config namespace (e.g., 'blog' or 'acme.blog')
     * @param string $viewNamespace View namespace (e.g., 'acme/blog')
     * @param string $translationNamespace Translation namespace (e.g., 'acme/blog')
     * @param string $routePrefix Route prefix (e.g., 'packages.blog')
     */
    public function __construct(protected string $vendor, protected string $package, protected string $configNamespace, protected string $viewNamespace, protected string $translationNamespace, protected string $routePrefix) {}

    /**
     * Get configuration value
     *
     * @param string|null $key Configuration key
     * @param mixed $default Default value if key not found
     *
     * @example
     * package_blog()->config('posts_per_page', 10)
     * package_blog()->config('enabled', true)
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return config($this->configNamespace);
        }

        return config("{$this->configNamespace}.{$key}", $default);
    }

    /**
     * Check if package is enabled
     *
     *
     * @example
     * if (package_blog()->enabled()) {
     *     // Package is enabled
     * }
     */
    public function enabled(): bool
    {
        return (bool) $this->config('enabled', true);
    }

    /**
     * Generate route URL
     *
     * @param string $name Route name (without prefix)
     * @param array $parameters Route parameters
     *
     * @example
     * package_blog()->route('index')
     * package_blog()->route('post.show', ['id' => 1])
     */
    public function route(string $name, array $parameters = []): string
    {
        return route("{$this->routePrefix}.{$name}", $parameters);
    }

    /**
     * Get asset URL
     *
     * @param string $path Asset path
     *
     * @example
     * package_blog()->asset('logo.png')
     * package_blog()->asset('css/style.css')
     */
    public function asset(string $path): string
    {
        return asset("vendor/{$this->package}/{$path}");
    }

    /**
     * Get view instance
     *
     * @param string $view View name (without namespace)
     * @param array $data View data
     *
     * @example
     * package_blog()->view('index', ['posts' => $posts])
     * package_blog()->view('posts.show', ['post' => $post])
     */
    public function view(string $view, array $data = []): View
    {
        return view("{$this->viewNamespace}::{$view}", $data);
    }

    /**
     * Get translation
     *
     * @param string $key Translation key (without namespace)
     * @param array $replace Replacement values
     * @param string|null $locale Locale override
     *
     * @example
     * package_blog()->trans('messages.welcome')
     * package_blog()->trans('messages.greeting', ['name' => 'John'])
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return __("{$this->translationNamespace}::{$key}", $replace, $locale);
    }

    /**
     * Get namespace for given type
     *
     * @param string $type Namespace type (view, translation, config)
     *
     * @throws InvalidArgumentException If type is unknown
     *
     * @example
     * package_blog()->namespace('view')  // 'acme/blog'
     * package_blog()->namespace('translation')  // 'acme/blog'
     * package_blog()->namespace('config')  // 'blog' or 'acme.blog'
     */
    public function namespace(string $type = 'view'): string
    {
        return match ($type) {
            'view' => $this->viewNamespace,
            'translation', 'trans', 'lang' => $this->translationNamespace,
            'config' => $this->configNamespace,
            default => throw new InvalidArgumentException("Unknown namespace type: {$type}"),
        };
    }

    /**
     * Get vendor name
     *
     *
     * @example
     * package_blog()->vendor()  // 'acme'
     */
    public function vendor(): string
    {
        return $this->vendor;
    }

    /**
     * Get package name
     *
     *
     * @example
     * package_blog()->package()  // 'blog'
     */
    public function package(): string
    {
        return $this->package;
    }

    /**
     * Get route prefix
     *
     *
     * @example
     * package_blog()->routePrefix()  // 'packages.blog'
     */
    public function routePrefix(): string
    {
        return $this->routePrefix;
    }

    /**
     * Get view namespace
     *
     *
     * @example
     * package_blog()->viewNamespace()  // 'acme/blog'
     */
    public function viewNamespace(): string
    {
        return $this->viewNamespace;
    }

    /**
     * Get translation namespace
     *
     *
     * @example
     * package_blog()->translationNamespace()  // 'acme/blog'
     */
    public function translationNamespace(): string
    {
        return $this->translationNamespace;
    }

    /**
     * Get config namespace
     *
     *
     * @example
     * package_blog()->configNamespace()  // 'blog' or 'acme.blog'
     */
    public function configNamespace(): string
    {
        return $this->configNamespace;
    }
}

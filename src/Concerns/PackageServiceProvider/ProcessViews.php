<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\View\FileViewFinder;
use Illuminate\Support\Facades\View;

trait ProcessViews
{
    protected function bootPackageViews(): self
    {
        if (! $this->package->hasViews) {
            return $this;
        }

        $viewNamespace = $this->package->viewNamespace();
        $viewsPath = $this->package->basePath('/resources/views');
        $vendorViews = realpath($viewsPath) ?: $viewsPath;
        $appViews = base_path("resources/views/vendor/{$viewNamespace}");

        $this->loadViewsFrom($vendorViews, $viewNamespace);

        // Blade's component-tag pattern is x[-\:]([\w\-\:\.]*) -- no forward slash -- so a tag
        // written against the canonical `vendor/package` namespace truncates at the slash and is
        // never compiled. Every other form viewNamespaces() lists (the hyphen prefix, and the
        // canonical slash form for a package registered under its hyphen name) is aliased over the
        // paths loadViewsFrom() just resolved, which include the application's published override
        // directory, so every spelling finds the same file and publishing an override still wins.
        $forms = array_values(array_diff($this->package->viewNamespaces(), [$viewNamespace]));

        if ($forms !== []) {
            // getHints() is on FileViewFinder rather than the interface, so an application running
            // a custom finder falls back to the package path alone. It loses the published-override
            // lookup for the aliases, but they still resolve.
            $finder = View::getFinder();
            $paths = $finder instanceof FileViewFinder
                ? ($finder->getHints()[$viewNamespace] ?? [$vendorViews])
                : [$vendorViews];

            foreach ($forms as $form) {
                View::addNamespace($form, $paths);
            }
        }

        if ($this->app->runningInConsole()) {
            $publishTag = $this->package->getNamespacedPublishTag('views');

            $this->publishes([$vendorViews => $appViews], $publishTag);
        }

        return $this;
    }
}

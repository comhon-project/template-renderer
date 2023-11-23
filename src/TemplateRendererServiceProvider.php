<?php

namespace Comhon\TemplateRenderer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TemplateRendererServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('template-renderer')
            ->hasConfigFile();
    }

    public function packageRegistered()
    {
        $this->app->singleton(TemplateManager::class, function ($app) {
            return new TemplateManager($app);
        });
    }
}

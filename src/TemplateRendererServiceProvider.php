<?php

namespace Comhon\TemplateRenderer;

use Comhon\TemplateRenderer\Locale\LaravelLocaleHandler;
use Comhon\TemplateRenderer\Locale\LocaleHandler;
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
        $this->app->bindIf(LocaleHandler::class, LaravelLocaleHandler::class);
        $this->app->singleton(TemplateManager::class, fn ($app) => new TemplateManager(
            $app['config']['template-renderer'],
            $app->make(LocaleHandler::class),
        ));
    }
}

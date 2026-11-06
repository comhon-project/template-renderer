<?php

namespace Comhon\TemplateRenderer\Locale;

use Illuminate\Contracts\Foundation\Application;

class LaravelLocaleHandler implements LocaleHandler
{
    public function __construct(private Application $app) {}

    public function getLocale(): ?string
    {
        return $this->app->getLocale();
    }

    public function setLocale(?string $locale): void
    {
        $this->app->setLocale($locale);
    }
}

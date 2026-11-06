<?php

namespace Comhon\TemplateRenderer\Locale;

/**
 * Gives access to the locale of the application.
 */
interface LocaleHandler
{
    public function getLocale(): ?string;

    public function setLocale(?string $locale): void;
}

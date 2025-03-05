<?php

namespace Comhon\TemplateRenderer\Renderers;

interface RendererInterface
{
    /**
     * Set de default locale
     */
    public function setDefaultLocale(string $locale);

    /**
     * Set de default timezone
     */
    public function setDefaultTimezone(string $timezone);

    /**
     * render template
     *
     * @param  string  $template  the template to render
     * @param  array  $replacements  values used for replacements
     * @param  string  $defaultLocale  the default locale that should be used
     * @param  string  $defaultTimezone  the default timezone that should be used
     * @param  string  $preferredTimezone  the timezone to use when needed based on reader preferences.
     */
    public function render(
        string $template,
        array $replacements,
        ?string $defaultLocale = null,
        ?string $defaultTimezone = null,
        ?string $preferredTimezone = null
    ): string;

    /**
     * verify if template is valid
     */
    public function validate(string $template);
}

<?php

namespace Comhon\TemplateRenderer\Tests\Support;

use Comhon\TemplateRenderer\Renderers\RendererInterface;

class TestTemplaterRender implements RendererInterface
{
    public function setDefaultLocale(string $locale) {}

    public function setDefaultTimezone(string $timezone) {}

    public function render(
        string $template,
        array $replacements,
        ?string $defaultLocale = null,
        ?string $defaultTimezone = null,
        ?string $preferredTimezone = null
    ): string {
        return str_replace('{{ to_replace }}', 'replaced', $template);
    }

    public function validate(string $template)
    {
        return true;
    }
}

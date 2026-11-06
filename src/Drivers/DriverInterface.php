<?php

namespace Comhon\TemplateRenderer\Drivers;

use Comhon\TemplateRenderer\RenderContext;

interface DriverInterface
{
    /**
     * Render template, the locale of the context is already applied.
     */
    public function render(string $template, array $replacements, RenderContext $context): string;

    /**
     * verify if template is valid
     */
    public function validate(string $template);
}

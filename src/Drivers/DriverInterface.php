<?php

namespace Comhon\TemplateRenderer\Drivers;

use Comhon\TemplateRenderer\Exceptions\InvalidTemplateException;
use Comhon\TemplateRenderer\RenderContext;

interface DriverInterface
{
    /**
     * Render template, the locale of the context is already applied.
     */
    public function render(string $template, array $replacements, RenderContext $context): string;

    /**
     * Verify if the template is valid.
     *
     * @throws InvalidTemplateException with a message meant for the author of the template
     */
    public function validate(string $template);
}

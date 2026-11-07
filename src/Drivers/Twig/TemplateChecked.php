<?php

namespace Comhon\TemplateRenderer\Drivers\Twig;

use Twig\Sandbox\SecurityError;

/**
 * Thrown by the validating policy to interrupt the render of a template once Twig checked its security.
 *
 * @internal
 */
final class TemplateChecked extends SecurityError
{
    public function __construct()
    {
        parent::__construct('The template passed the sandbox security check');
    }
}

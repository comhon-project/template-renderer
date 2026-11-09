<?php

namespace Comhon\TemplateRenderer\Drivers\Twig;

use Twig\Error\Error;

/**
 * Interrupts the render of a template once Twig checked its security (a Twig error is not wrapped by Twig).
 *
 * @internal
 */
final class TemplateChecked extends Error
{
    public function __construct()
    {
        // line 0, otherwise Twig guesses one from the backtrace
        parent::__construct('The template passed the sandbox security check', 0);
    }
}

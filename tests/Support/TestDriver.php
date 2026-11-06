<?php

namespace Comhon\TemplateRenderer\Tests\Support;

use Comhon\TemplateRenderer\Drivers\DriverInterface;
use Comhon\TemplateRenderer\RenderContext;

class TestDriver implements DriverInterface
{
    public function render(string $template, array $replacements, RenderContext $context): string
    {
        return str_replace('{{ to_replace }}', 'replaced', $template);
    }

    public function validate(string $template)
    {
        return true;
    }

    public function customMethod(): string
    {
        return 'custom';
    }
}

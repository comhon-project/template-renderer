<?php

namespace Comhon\TemplateRenderer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Comhon\TemplateRenderer\Renderers\RendererInterface getRenderer(string $name = null)
 * @method static void extend(string $driver, \Closure $resolver)
 * @method static string getDefaultRendererName()
 * @method static void setDefaultLocale(string $locale)
 * @method static void setDefaultTimezone(string $timezone)
 * @method static string render(string $template, array $replacements, string $defaultLocale = null, string $defaultTimezone = null, string $preferredTimezone = null)
 * @method static string validate(string $template)
 *
 * @see \Comhon\TemplateRenderer\TemplateManager
 */
class Template extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Comhon\TemplateRenderer\TemplateManager::class;
    }
}

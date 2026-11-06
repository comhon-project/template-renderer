<?php

namespace Comhon\TemplateRenderer\Facades;

use Comhon\TemplateRenderer\TemplateManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string render(string $template, array|\Closure $replacements, ?\Comhon\TemplateRenderer\RenderOptions $options = null)
 * @method static mixed validate(string $template)
 * @method static void setDefaultLocale(string $locale)
 * @method static void setDefaultTimezone(string $timezone)
 * @method static \Comhon\TemplateRenderer\Renderer using(string $name)
 * @method static void extend(string $driver, \Closure $creator)
 * @method static string getDefaultRendererName()
 *
 * @see TemplateManager
 */
class Template extends Facade
{
    protected static function getFacadeAccessor()
    {
        return TemplateManager::class;
    }
}

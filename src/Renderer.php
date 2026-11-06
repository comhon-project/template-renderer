<?php

namespace Comhon\TemplateRenderer;

use Comhon\TemplateRenderer\Drivers\DriverInterface;
use Comhon\TemplateRenderer\Locale\LocaleHandler;

/**
 * Renders templates with a driver, other calls are forwarded to the driver.
 */
class Renderer
{
    private ?string $defaultLocale = null;

    private ?string $defaultTimezone = null;

    public function __construct(
        private DriverInterface $driver,
        private ?LocaleHandler $localeHandler = null,
    ) {}

    public function setDefaultLocale(string $locale): void
    {
        $this->defaultLocale = $locale;
    }

    public function setDefaultTimezone(string $timezone): void
    {
        $this->defaultTimezone = $timezone;
    }

    /**
     * Render a template. Replacements may be a closure that receives the render context.
     *
     * @param  array|\Closure(RenderContext): array  $replacements
     */
    public function render(string $template, array|\Closure $replacements, ?RenderOptions $options = null): string
    {
        $context = $this->createContext($options);
        $originalAppLocale = $this->localeHandler?->getLocale();
        $originalIntlLocale = \Locale::getDefault();
        $intlLocaleChanged = false;
        $appLocaleApplied = false;

        try {
            $locale = $context->locale();
            if ($locale !== null && $locale !== $originalIntlLocale) {
                $intlLocaleChanged = true;
                \Locale::setDefault($locale);
            }
            if ($this->localeHandler !== null && $locale !== null) {
                $appLocaleApplied = true;
                $this->localeHandler->setLocale($locale);
            }

            if ($replacements instanceof \Closure) {
                $replacements = $this->resolveReplacements($replacements, $context);
            }

            return $this->driver->render($template, $replacements, $context);
        } finally {
            if ($intlLocaleChanged) {
                \Locale::setDefault($originalIntlLocale);
            }
            if ($appLocaleApplied) {
                $this->localeHandler->setLocale($originalAppLocale);
            }
        }
    }

    public function createContext(?RenderOptions $options = null): RenderContext
    {
        return new RenderContext($options ?? new RenderOptions, new RenderOptions(
            locale: $this->defaultLocale ?? $this->localeHandler?->getLocale(),
            timezone: $this->defaultTimezone,
        ));
    }

    public function validate(string $template)
    {
        return $this->driver->validate($template);
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    private function resolveReplacements(\Closure $replacements, RenderContext $context): array
    {
        $resolved = $replacements($context);
        if (is_array($resolved)) {
            return $resolved;
        }
        if ($resolved instanceof \Traversable) {
            return iterator_to_array($resolved);
        }

        throw new \UnexpectedValueException(
            'Replacements closure must return an array or a Traversable, '.get_debug_type($resolved).' given'
        );
    }

    public function __call($method, $parameters)
    {
        return $this->driver->$method(...$parameters);
    }
}

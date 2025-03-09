<?php

namespace Comhon\TemplateRenderer\Renderers;

trait InteractWithApp
{
    /**
     * Apply given locale as default locale, then call given callback, then finally restore original locale.
     */
    public function applyDefaultLocale(?string $locale, $app, \Closure $callback)
    {
        if (is_object($app) && method_exists($app, 'getLocale')) {
            $originalAppLocale = $app->getLocale();
        }
        $locale ??= $originalAppLocale ?? null;

        if ($locale) {
            $originalIntlLocale = \Locale::getDefault();
            \Locale::setDefault($locale);
            if (is_object($app) && method_exists($app, 'setLocale')) {
                $app->setLocale($locale);
            }
        }

        try {
            return $callback($locale);
        } finally {
            if (isset($originalIntlLocale)) {
                \Locale::setDefault($originalIntlLocale);
            }
            if (isset($originalAppLocale)) {
                $app->setLocale($originalAppLocale);
            }
        }
    }
}

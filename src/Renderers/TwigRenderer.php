<?php

namespace Comhon\TemplateRenderer\Renderers;

use Illuminate\Support\Facades\App;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;

class TwigRenderer implements RendererInterface
{
    /**
     * @var \Twig\Loader\ArrayLoader
     */
    private $loader;

    /**
     * @var \Twig\Environment
     */
    private $twig;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $timezone;

    public function __construct(array $config = [])
    {
        $this->loader = new ArrayLoader([]);
        $this->twig = new Environment($this->loader, $config);
        $this->twig->addExtension(new IntlExtension());
    }

    /**
     * Set de default locale
     */
    public function setDefaultLocale(string $locale)
    {
        $this->locale = $locale;
    }

    /**
     * Set de default timezone
     */
    public function setDefaultTimezone(string $timezone)
    {
        $this->timezone = $timezone;
    }

    /**
     * render template
     *
     * @param  string  $template the template to render
     * @param  array  $replacements values used for replacements
     * @param  string  $defaultLocale the default locale that should be used
     * @param  string  $defaultTimezone the default timezone that should be used
     * @param  string  $preferredTimezone the timezone to use when needed based on reader preferences.
     */
    public function render(
        string $template,
        array $replacements,
        string $defaultLocale = null,
        string $defaultTimezone = null,
        string $preferredTimezone = null
    ): string {
        $name = hash(\PHP_VERSION_ID < 80100 ? 'sha256' : 'xxh128', $template, false);
        if (!$this->loader->exists($name)) {
            $this->loader->setTemplate($name, $template);
        }
        $defaultTimezone ??= $this->timezone ?? date_default_timezone_get();
        $this->twig->getExtension(CoreExtension::class)->setTimezone($defaultTimezone);
        $this->twig->addGlobal('default_timezone', $defaultTimezone);
        $this->twig->addGlobal('preferred_timezone', $preferredTimezone ?? $defaultTimezone);

        $original = App::getLocale();
        $defaultLocale ??= $this->locale ?? $original;
        $this->twig->addGlobal('default_locale', $defaultLocale);

        try {
            \Locale::setDefault($defaultLocale);
            $computed = $this->twig->render($name, $replacements);
        } finally {
            \Locale::setDefault($original);
        }

        return $computed;
    }

    /**
     * verify if template is valid
     *
     * @param  string  $template
     */
    public function validate($template)
    {
        $name = hash(\PHP_VERSION_ID < 80100 ? 'sha256' : 'xxh128', $template, false);
        $this->twig->parse($this->twig->tokenize(new \Twig\Source($template, $name)));
    }
}

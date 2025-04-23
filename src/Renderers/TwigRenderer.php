<?php

namespace Comhon\TemplateRenderer\Renderers;

use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;

class TwigRenderer implements RendererInterface
{
    use InteractWithApp;

    private $app;

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

    public function __construct(array $config, $app)
    {
        $this->app = $app;
        $this->loader = new ArrayLoader([]);
        $this->twig = new Environment($this->loader, $config);
        $this->twig->addExtension(new IntlExtension);
        $this->twig->addFunction(new \Twig\TwigFunction('__', function ($key = null, $replace = [], $locale = null) {
            return __($key, $replace, $locale);
        }));
        $this->twig->addFunction(new \Twig\TwigFunction('trans', function ($key = null, $replace = [], $locale = null) {
            return trans($key, $replace, $locale);
        }));
        $this->twig->addFunction(new \Twig\TwigFunction('trans_choice', function ($key, $number, array $replace = [], $locale = null) {
            return trans_choice($key, $number, $replace, $locale);
        }));
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
     * @see Comhon\TemplateRenderer\Renderers\RendererInterface::render()
     */
    public function render(
        string $template,
        array $replacements,
        ?string $defaultLocale = null,
        ?string $defaultTimezone = null,
        ?string $preferredTimezone = null
    ): string {
        $name = hash(\PHP_VERSION_ID < 80100 ? 'sha256' : 'xxh128', $template, false);
        if (! $this->loader->exists($name)) {
            $this->loader->setTemplate($name, $template);
        }
        $defaultTimezone ??= $this->timezone ?? date_default_timezone_get();
        $this->twig->getExtension(CoreExtension::class)->setTimezone($defaultTimezone);
        $this->twig->addGlobal('default_timezone', $defaultTimezone);
        $this->twig->addGlobal('preferred_timezone', $preferredTimezone ?? $defaultTimezone);

        return $this->applyDefaultLocale(
            $defaultLocale ?? $this->locale,
            $this->app,
            function ($locale) use ($name, $replacements) {
                $this->twig->addGlobal('default_locale', $locale);

                return $this->twig->render($name, $replacements);
            }
        );
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

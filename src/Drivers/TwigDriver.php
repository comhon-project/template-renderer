<?php

namespace Comhon\TemplateRenderer\Drivers;

use Comhon\TemplateRenderer\RenderContext;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\TwigFunction;

class TwigDriver implements DriverInterface
{
    private const CONTEXT_GLOBALS = ['default_locale', 'default_timezone', 'preferred_timezone'];

    /**
     * @var ArrayLoader
     */
    private $loader;

    /**
     * @var Environment
     */
    private $twig;

    public function __construct(array $config)
    {
        $this->loader = new ArrayLoader([]);
        $this->twig = new Environment($this->loader, $config);
        $this->twig->addExtension(new IntlExtension);
        $this->twig->addFunction(new TwigFunction('__', '__'));
        $this->twig->addFunction(new TwigFunction('trans', 'trans'));
        $this->twig->addFunction(new TwigFunction('trans_choice', 'trans_choice'));
        foreach (self::CONTEXT_GLOBALS as $global) {
            $this->twig->addGlobal($global, null);
        }
    }

    /**
     * @see DriverInterface::render()
     */
    public function render(string $template, array $replacements, RenderContext $context): string
    {
        $name = $this->templateName($template);
        if (! $this->loader->exists($name)) {
            $this->loader->setTemplate($name, $template);
        }

        // the environment is shared, its state is restored after the render
        $coreExtension = $this->twig->getExtension(CoreExtension::class);
        $originalTimezone = $coreExtension->getTimezone();
        $originalGlobals = array_intersect_key($this->twig->getGlobals(), array_flip(self::CONTEXT_GLOBALS));

        $coreExtension->setTimezone($context->timezone());
        $this->twig->addGlobal('default_locale', $context->locale());
        $this->twig->addGlobal('default_timezone', $context->timezone());
        $this->twig->addGlobal('preferred_timezone', $context->preferredTimezone());

        try {
            return $this->twig->render($name, $replacements);
        } finally {
            $coreExtension->setTimezone($originalTimezone);
            foreach ($originalGlobals as $global => $value) {
                $this->twig->addGlobal($global, $value);
            }
        }
    }

    /**
     * verify if template is valid
     *
     * @param  string  $template
     */
    public function validate($template)
    {
        $name = $this->templateName($template);
        $this->twig->parse($this->twig->tokenize(new Source($template, $name)));
    }

    private function templateName(string $template): string
    {
        $name = hash('xxh128', $template);

        // xxh128 is fast but not collision resistant: a name already used by another source falls back to sha256
        if ($this->loader->exists($name) && $this->loader->getSourceContext($name)->getCode() !== $template) {
            $name = hash('sha256', $template);
        }

        return $name;
    }
}

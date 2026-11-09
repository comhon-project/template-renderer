<?php

namespace Comhon\TemplateRenderer\Drivers;

use Comhon\TemplateRenderer\Drivers\Twig\TemplateChecked;
use Comhon\TemplateRenderer\Drivers\Twig\ValidationPolicy;
use Comhon\TemplateRenderer\Exceptions\InvalidTemplateException;
use Comhon\TemplateRenderer\RenderContext;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\Sandbox;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityPolicy;
use Twig\Sandbox\SecurityPolicyInterface;
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

    private ?SecurityPolicyInterface $policy = null;

    /**
     * Set when the renderer is sandboxed, every render goes through it.
     */
    private ?Sandbox $sandbox = null;

    /**
     * Sandbox of a dedicated environment, created when a template is validated.
     */
    private ?Sandbox $validationSandbox = null;

    public function __construct(private array $config)
    {
        $this->loader = new ArrayLoader([]);
        $this->twig = $this->createEnvironment();

        if (isset($config['sandbox']) && ! is_array($config['sandbox'])) {
            throw new \InvalidArgumentException("Twig 'sandbox' config must be an array, ".get_debug_type($config['sandbox']).' given');
        }
        if ($config['sandbox']['enabled'] ?? false) {
            $this->policy = $this->createSecurityPolicy($config['sandbox']);
            $this->sandbox = new Sandbox($this->twig, $this->policy);
        }
    }

    /**
     * Whether templates are rendered in the Twig sandbox.
     */
    public function isSandboxed(): bool
    {
        return $this->sandbox !== null;
    }

    /**
     * @see DriverInterface::render()
     */
    public function render(string $template, array $replacements, RenderContext $context): string
    {
        $name = $this->registerTemplate($template);

        // the environment is shared, its state is restored after the render
        $coreExtension = $this->twig->getExtension(CoreExtension::class);
        $originalTimezone = $coreExtension->getTimezone();
        $originalGlobals = array_intersect_key($this->twig->getGlobals(), array_flip(self::CONTEXT_GLOBALS));

        $coreExtension->setTimezone($context->timezone());
        $this->twig->addGlobal('default_locale', $context->locale());
        $this->twig->addGlobal('default_timezone', $context->timezone());
        $this->twig->addGlobal('preferred_timezone', $context->preferredTimezone());

        try {
            return $this->sandbox !== null
                ? $this->sandbox->render($name, $replacements)
                : $this->twig->render($name, $replacements);
        } finally {
            $coreExtension->setTimezone($originalTimezone);
            foreach ($originalGlobals as $global => $value) {
                $this->twig->addGlobal($global, $value);
            }
        }
    }

    /**
     * @see DriverInterface::validate()
     *
     * @param  string  $template
     */
    public function validate($template)
    {
        $name = $this->templateName($template);
        try {
            $this->twig->parse($this->twig->tokenize(new Source($template, $name)));
            if ($this->sandbox !== null) {
                $this->validateInSandbox($template, $name);
            }
        } catch (SyntaxError|SecurityError|LoaderError $e) {
            // only the errors about the template itself: Twig wraps any other failure in one of them, with a previous
            if ($e->getPrevious() !== null) {
                throw $e;
            }
            // the message of Twig, without the internal name of the template (a hash)
            throw new InvalidTemplateException(rtrim($e->getRawMessage(), '.'), $e->getTemplateLine() > 0 ? $e->getTemplateLine() : null, $e);
        }
    }

    /**
     * The sandbox checks the tags, filters, functions and tests of a template when it starts rendering,
     * before evaluating anything: the validation sandbox interrupts the render right after.
     */
    private function validateInSandbox(string $template, string $name): void
    {
        $this->registerTemplate($template);
        $this->validationSandbox ??= new Sandbox($this->createEnvironment(), new ValidationPolicy($this->policy));
        try {
            $this->validationSandbox->render($name, []);
        } catch (TemplateChecked) {
            // thrown by the validation policy once Twig accepted the template: it is valid
        }
    }

    /**
     * Give the template to the loader, under its name.
     */
    private function registerTemplate(string $template): string
    {
        $name = $this->templateName($template);
        if (! $this->loader->exists($name)) {
            $this->loader->setTemplate($name, $template);
        }

        return $name;
    }

    private function createEnvironment(): Environment
    {
        $twig = new Environment($this->loader, $this->config);
        $twig->addExtension(new IntlExtension);
        $twig->addFunction(new TwigFunction('__', '__'));
        $twig->addFunction(new TwigFunction('trans', 'trans'));
        $twig->addFunction(new TwigFunction('trans_choice', 'trans_choice'));
        foreach (self::CONTEXT_GLOBALS as $global) {
            $twig->addGlobal($global, null);
        }

        return $twig;
    }

    private function createSecurityPolicy(array $config): SecurityPolicyInterface
    {
        if (isset($config['policy'])) {
            $class = $config['policy'];
            if (! is_string($class) || ! is_subclass_of($class, SecurityPolicyInterface::class)) {
                throw new \InvalidArgumentException(
                    "Twig sandbox policy '".(is_string($class) ? $class : get_debug_type($class))."' must be a class implementing "
                    .SecurityPolicyInterface::class
                );
            }
            if ((new \ReflectionClass($class))->getConstructor()?->getNumberOfRequiredParameters()) {
                throw new \InvalidArgumentException("Twig sandbox policy '$class' must be instantiable without arguments");
            }

            return new $class;
        }

        $policy = new SecurityPolicy(
            allowedTags: $config['allowed_tags'] ?? [],
            allowedFilters: $config['allowed_filters'] ?? [],
            allowedMethods: $config['allowed_methods'] ?? [],
            allowedProperties: $config['allowed_properties'] ?? [],
            allowedFunctions: $config['allowed_functions'] ?? [],
            allowedTests: $config['allowed_tests'] ?? [],
        );
        // strict mode: nothing is implicitly allowed (Twig 4.0 behavior)
        $policy->setStrict(true);

        return $policy;
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

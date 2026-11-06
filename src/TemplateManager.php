<?php

namespace Comhon\TemplateRenderer;

use Comhon\TemplateRenderer\Drivers\DriverInterface;
use Comhon\TemplateRenderer\Drivers\TwigDriver;
use Comhon\TemplateRenderer\Locale\LocaleHandler;

class TemplateManager
{
    /**
     * The array of renderers.
     *
     * @var array
     */
    protected $renderers = [];

    /**
     * The registered custom driver creators, by driver name.
     *
     * @var array
     */
    protected $customCreators = [];

    public function __construct(
        protected array $config,
        protected ?LocaleHandler $localeHandler = null,
    ) {}

    /**
     * Get the renderer with the given name.
     */
    public function using(string $name): Renderer
    {
        return $this->getRenderer($name);
    }

    /**
     * Get a renderer instance by name, the default one if no name is given.
     */
    protected function getRenderer(?string $name = null): Renderer
    {
        $name = $name ?: $this->getDefaultRendererName();

        return $this->renderers[$name] ??= new Renderer($this->resolve($name), $this->localeHandler);
    }

    /**
     * Resolve the driver of the given renderer.
     */
    protected function resolve(string $name): DriverInterface
    {
        $config = $this->getRendererConfig($name);
        if (! isset($config)) {
            throw new \Exception("template renderer name '$name' is not defined");
        }
        $driverName = $config['driver'] ?? null;

        if ($driverName !== null && isset($this->customCreators[$driverName])) {
            $driver = $this->customCreators[$driverName]($config);
        } elseif ($driverName === 'twig') {
            $driver = new TwigDriver($config);
        } else {
            throw new \Exception("template renderer driver '$driverName' is not defined");
        }
        if (! ($driver instanceof DriverInterface)) {
            throw new \Exception(
                "Driver creator '$driverName' should return instance of "
                .DriverInterface::class
            );
        }

        return $driver;
    }

    /**
     * Register a custom driver creator, it receives the renderer configuration.
     *
     * @param  \Closure(array): DriverInterface  $creator
     */
    public function extend(string $driver, \Closure $creator)
    {
        $this->customCreators[$driver] = $creator;
    }

    /**
     * Get the renderer configuration.
     */
    protected function getRendererConfig(string $name): ?array
    {
        return $this->config['renderers'][$name] ?? null;
    }

    /**
     * Get the default renderer name.
     */
    public function getDefaultRendererName(): string
    {
        return $this->config['default_renderer'] ?? throw new \Exception('default template renderer is not defined');
    }

    /**
     * Dynamically call the default renderer instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->getRenderer()->$method(...$parameters);
    }
}

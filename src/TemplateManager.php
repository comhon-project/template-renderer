<?php

namespace Comhon\TemplateRenderer;

use Comhon\TemplateRenderer\Renderers\RendererInterface;
use Comhon\TemplateRenderer\Renderers\TwigRenderer;

class TemplateManager
{
    protected $app;

    /**
     * The array of renderers.
     *
     * @var array
     */
    protected $renderers = [];

    /**
     * The registered custom driver resolvers.
     *
     * @var array
     */
    protected $customResolvers = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Get a render instance by name.
     */
    public function getRenderer(?string $name = null): RendererInterface
    {
        $name = $name ?: $this->getDefaultRendererName();

        return $this->renderers[$name] ??= $this->resolve($name);
    }

    /**
     * Get the cache connection configuration.
     */
    protected function resolve(string $name): RendererInterface
    {
        $render = null;
        if (isset($this->customResolvers[$name])) {
            $render = $this->customResolvers[$name]($this->app);
        } else {
            $config = $this->getRendererConfig($name);
            if (! isset($config)) {
                throw new \Exception("template renderer name '$name' is not defined");
            }
            $driver = $config['driver'] ?? null;
            switch ($driver) {
                case 'twig':
                    $render = new TwigRenderer($config, $this->app);
                    break;
                default:
                    throw new \Exception("template renderer driver '$driver' is not defined");
            }
        }
        if (! ($render instanceof RendererInterface)) {
            throw new \Exception(
                "Renderer resolver '$name' should return instance of "
                .RendererInterface::class
            );
        }

        return $render;
    }

    /**
     * Register a custom driver resolver Closure.
     */
    public function extend(string $name, \Closure $resolver)
    {
        $this->customResolvers[$name] = $resolver;
    }

    /**
     * Get the renderer configuration.
     */
    protected function getRendererConfig(string $name): ?array
    {
        return $this->app['config']["template-renderer.renderers.{$name}"];
    }

    /**
     * Get the default renderer name.
     */
    public function getDefaultRendererName(): string
    {
        return $this->app['config']['template-renderer.default_renderer'];
    }

    /**
     * Dynamically call the default driver instance.
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

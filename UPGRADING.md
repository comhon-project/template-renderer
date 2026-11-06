# Upgrading

## From 1.x to 2.x

### What changed in the architecture

In 1.x, the template manager gave direct access to the renderer implementation (e.g. `TwigRenderer`).
In 2.x, these implementations are called **drivers**, and each one is wrapped in a `Comhon\TemplateRenderer\Renderer`.
The renderer resolves the render context, applies the locale and calls the replacements closure, then lets the driver render.

```
Template / TemplateManager → Renderer → Driver (e.g. TwigDriver)
```

### Rendering

- **The locale and timezones are given with a `RenderOptions` object:**

  ```php
  use Comhon\TemplateRenderer\RenderOptions;

  // 1.x
  Template::render($template, $replacements, 'fr', 'Europe/Paris', 'Asia/Tokyo');

  // 2.x
  Template::render($template, $replacements, new RenderOptions('fr', 'Europe/Paris', 'Asia/Tokyo'));
  ```

- **Empty locale:** an empty string is no longer replaced by the default locale. Give `null` to use the default locale.
- **Replacements** can now also be a closure (see the README).
- **`getRenderer()` is renamed `using()`:**

  ```php
  // 1.x
  Template::getRenderer('my-renderer')->render($template, $replacements);

  // 2.x
  Template::using('my-renderer')->render($template, $replacements);
  ```

  `getRenderer()` without a name has no equivalent: call the methods directly on the facade
  (e.g. `Template::myMethod()`), as already possible in 1.x.

  `using()` returns the `Renderer`: other calls than `render()` are forwarded to the driver (available with `getDriver()`).

### Without Laravel

`TemplateManager` no longer receives the app, but its configuration and an optional locale handler
(see "Without Laravel" in the README). The configuration is read once, when the manager is created.

```php
// 1.x
$templateManager = new TemplateManager($app);

// 2.x
$templateManager = new TemplateManager(
    ['default_renderer' => 'twig', 'renderers' => ['twig' => ['driver' => 'twig']]],
    new MyLocaleHandler, // optional
);
```

### Custom drivers

**Renamed classes**

| 1.x | 2.x |
|---|---|
| `Comhon\TemplateRenderer\Renderers\RendererInterface` | `Comhon\TemplateRenderer\Drivers\DriverInterface` |
| `Comhon\TemplateRenderer\Renderers\TwigRenderer` | `Comhon\TemplateRenderer\Drivers\TwigDriver` |
| `Comhon\TemplateRenderer\Renderers\InteractWithApp` | removed |

**Interface changes**

The driver only renders: `setDefaultLocale()` and `setDefaultTimezone()` are now handled by the renderer, and are removed from the interface.

- `render()` receives the render context instead of the locale and timezones:

  ```php
  // 1.x
  public function render(string $template, array $replacements, ?string $defaultLocale = null, ?string $defaultTimezone = null, ?string $preferredTimezone = null): string;

  // 2.x
  public function render(string $template, array $replacements, RenderContext $context): string;
  ```

- Drivers no longer apply the locale: it is applied before `render()` is called.

```php
use Comhon\TemplateRenderer\Drivers\DriverInterface;
use Comhon\TemplateRenderer\RenderContext;

class MyDriver implements DriverInterface
{
    public function validate(string $template) {}

    public function render(string $template, array $replacements, RenderContext $context): string
    {
        // render with $context->locale(), $context->timezone() and $context->preferredTimezone()
    }
}
```

**Registration**

`extend()` now registers a **driver**, used by renderers declared in the config.
Its closure receives the renderer configuration instead of the app.

```php
// 1.x
Template::extend('my-renderer', fn ($app) => new MyTemplateRenderer($app));

// 2.x
Template::extend('my-driver', fn (array $config) => new MyDriver($config));

// configuration (array given to TemplateManager, or config/template-renderer.php with Laravel)
'renderers' => [
    'my-renderer' => ['driver' => 'my-driver'],
],
```

# PHP Template Renderer

Render templates (emails, documents, notifications…) without tying your code to a template engine.

- Templates are rendered by a **driver**. [Twig](https://twig.symfony.com) is provided, and you can add your own.
- Locale and timezone are handled per render, including dates without timezone (e.g. a meeting at 14:00).
- Designed for Laravel, but usable in any PHP project.
- Templates written by your users can be rendered in the Twig sandbox (see [Security](#security)).

## Installation

```bash
composer require comhon-project/template-renderer
```

In a Laravel project, you can publish the config file:

```bash
php artisan vendor:publish --tag="template-renderer-config"
```

## Usage

### Laravel

```php
use Comhon\TemplateRenderer\Facades\Template;

echo Template::render('Hello {{ user.name }}!', ['user' => ['name' => 'John']]);
// Hello John!
```

### Without Laravel

Create the template manager once (e.g. as a singleton) with its configuration:

```php
use Comhon\TemplateRenderer\TemplateManager;

$config = [
    'default_renderer' => 'twig',
    'renderers' => [
        'twig' => ['driver' => 'twig'],
    ],
];

$templateManager = new TemplateManager($config);

echo $templateManager->render('Hello {{ user.name }}!', ['user' => ['name' => 'John']]);
// Hello John!
```

To use the locale of your application, give a locale handler as second argument.
It tells the library how to read and change your application locale:

```php
use Comhon\TemplateRenderer\Locale\LocaleHandler;

class MyLocaleHandler implements LocaleHandler
{
    public function getLocale(): ?string { /* return your application locale */ }
    public function setLocale(?string $locale): void { /* change your application locale */ }
}

$templateManager = new TemplateManager($config, new MyLocaleHandler);
```

### Locale and timezone

A render can be given a locale, a timezone and a preferred timezone (the reader's timezone):

```php
use Comhon\TemplateRenderer\RenderOptions;

Template::render($template, $replacements, new RenderOptions(locale: 'fr', timezone: 'Europe/Paris', preferredTimezone: 'Asia/Tokyo'));
```

To render for a recipient, a method of your own can build the options:

```php
class User
{
    public function toRenderOptions(): RenderOptions
    {
        return new RenderOptions(locale: $this->locale, preferredTimezone: $this->timezone);
    }
}

Template::render($template, $replacements, $user->toRenderOptions());
```

When a value is missing, the default one is used:

- locale: the renderer default locale, otherwise the application locale;
- timezone: the renderer default timezone, otherwise the PHP default timezone;
- preferred timezone: same as the timezone above.

Defaults are defined per renderer: `Template::setDefaultLocale()` and `Template::setDefaultTimezone()` set those of the default renderer,
`Template::using('my-renderer')->setDefaultLocale()` those of another one.
Setting them creates the renderer, so a custom driver must be registered (see `extend()` below) beforehand.

During the render, the locale is applied to the application, so translations (e.g. `__()`) use it.
In Twig templates, these values are available as `default_locale`, `default_timezone` and `preferred_timezone`.

### Building replacements from the render context

An array of replacements is built before the render, without knowing its final locale and timezone.
Replacements can also be given as a closure, which is only called at render time.
It receives the render context (`Comhon\TemplateRenderer\RenderContext`), which gives the resolved `locale()`, `timezone()` and `preferredTimezone()`,
so replacements can depend on them:

```php
use Comhon\TemplateRenderer\RenderContext;

$templateManager->render($template, fn (RenderContext $context) => [
    // full date in the render timezone and locale (e.g. "samedi 12 décembre 2026 à 15:00")
    'ordered_at' => IntlDateFormatter::formatObject(
        $order->created_at->setTimezone(new DateTimeZone($context->timezone())),
        [IntlDateFormatter::FULL, IntlDateFormatter::SHORT],
        $context->locale(),
    ),
]);
```

With Laravel, the app locale is set to the render locale while the closure is called,
so Laravel translations and Carbon formatting use it:

```php
Template::render($template, fn (RenderContext $context) => [
    'ordered_at' => $order->created_at->tz($context->timezone())->isoFormat('LLLL'),
    'title' => __('emails.title'),
]);
```

### Dates without timezone

Some dates have no timezone: a meeting at 14:00, a birthday. Build them from the render context:

- `$context->dateTime($value)` returns a `PlainDateTime`: the date and time placed in the render timezone.
  It behaves like any date: displayed as is, converted when another timezone is requested, comparable.
- `$context->date($value)` returns a `PlainDate`: a day that never shifts, even when another timezone is requested.

Both return `null` for a `null` value (handy for nullable columns).

```php
Template::render(
    "{{ meeting|date('H:i') }} / {{ meeting|date('H:i', 'Asia/Shanghai') }} / {{ birthday|date('d/m', 'America/New_York') }}",
    fn (RenderContext $context) => [
        'meeting' => $context->dateTime('2026-12-12 14:00'),
        'birthday' => $context->date('2026-12-12'),
    ],
    new RenderOptions(timezone: 'Europe/Paris'),
);
// 14:00 / 21:00 / 12/12 (a regular date at midnight in Paris would give 11/12 in New York)
```

The ideal value is a string without timezone (`Y-m-d H:i:s` or `Y-m-d`).
As renders often run asynchronously (e.g. in queued jobs), where a failure is costly, other values are tolerated:

- **The timezone of the value is ignored**: the date is taken as written (`2026-12-12 14:00 UTC` gives 14:00 in the render timezone).
  If the timezone of your value matters, don't use these classes: pass the date as is.
- Any `DateTimeInterface` or date string readable by PHP is accepted.
  Only a date or time that doesn't exist (e.g. `2026-02-30`, `24:00`), or a year outside 1 to 9999, throws an exception.
- Relative strings (e.g. `today`) are interpreted in the render timezone.
  Strings are read by PHP: a year alone (e.g. `2026`) is read as a time (20:26 today).

Limits to be aware of:

- A time that doesn't exist in the render timezone (daylight saving time change) is shifted by PHP (02:30 becomes 03:30).
- A `PlainDate` keeps its day only through template filters and its own `setTimezone()`.
  Other conversions (e.g. `Carbon::instance()`, `IntlDateFormatter`) see it as midnight in the render timezone and may change the day.

### Using another renderer

`render()` uses the default renderer (`default_renderer` in the config). To use another one:

```php
Template::using('my-renderer')->render($template, $replacements);
```

Any option of a Twig renderer is given to Twig as is: a renderer with `'autoescape' => false` renders plain text
(mail subjects, text mails) without HTML escaping.

## Security

Twig treats templates as trusted code. When templates are written by untrusted authors (e.g. your users),
render them in the [Twig sandbox](https://twig.symfony.com/doc/3.x/sandbox.html): the `sandbox` block of a Twig renderer
lists what templates may use. Everything else is rejected: `render()` throws a `Twig\Sandbox\SecurityError`,
`validate()` an `InvalidTemplateException`.

```php
'renderers' => [
    'twig' => [
        'driver' => 'twig',
        'sandbox' => [
            'enabled' => true,
            'allowed_tags' => ['if', 'for', 'set', /* ... */],
            'allowed_filters' => ['escape', 'e', 'upper', 'date', /* ... */],
            'allowed_functions' => ['__', 'trans', 'trans_choice', 'date', /* ... */],
            'allowed_tests' => [],
            'allowed_methods' => [\DateTimeInterface::class => ['__toString', 'format']],
            'allowed_properties' => [App\Models\User::class => ['name']],
            // 'policy' => App\Templates\TemplateSecurityPolicy::class,
        ],
    ],
],
```

The lists are those of the Twig [security policy](https://twig.symfony.com/doc/3.x/sandbox.html#configuring-the-security-policy), in strict mode,
and `policy` replaces them with your own class implementing `Twig\Sandbox\SecurityPolicyInterface` (instantiated without arguments).
The sandbox is disabled by default, and the configuration file ships with lists holding everything that cannot run code nor reach anything outside the template.

- `Template::validate()` and the validation rule report the tags, filters, functions and tests that are not allowed,
  in the template itself: templates it includes, extends or embeds (the body of an `embed` included) are checked when rendered.
  Methods and properties depend on the objects given at render time: they are only checked when rendering
  (see [Objects](https://twig.symfony.com/doc/3.x/sandbox.html#objects)). Arrays and scalars are not checked:
  the simplest option is to pass arrays and computed values in the replacements closure.
  Like a rendered template, a validated one is compiled and kept in memory for the life of the process.
- A `Stringable` object given to a filter, e.g. a Carbon date to `date`, needs its `__toString` allowed.
- The sandbox is configured per renderer: an entry of `renderers` has one or not.
- Read [what the sandbox does not protect against](https://twig.symfony.com/doc/3.x/sandbox.html#what-the-sandbox-does-not-protect-against),
  and keep Twig up to date.

## Custom drivers

### 1. Implement the driver

Implement `Comhon\TemplateRenderer\Drivers\DriverInterface`:

```php
use Comhon\TemplateRenderer\Drivers\DriverInterface;
use Comhon\TemplateRenderer\RenderContext;

class MyDriver implements DriverInterface
{
    public function __construct(private array $config) {}

    public function render(string $template, array $replacements, RenderContext $context): string
    {
        // render with $context->locale(), $context->timezone() and $context->preferredTimezone()
    }

    public function validate(string $template) {}
}
```

The render context is resolved before `render()` is called, and its locale is already applied to the application.
A driver can ignore the values it doesn't handle (e.g. the timezones for an engine without date formatting).
In `validate()`, throw a `Comhon\TemplateRenderer\Exceptions\InvalidTemplateException` when the template is invalid: the validation rule reports its message to the author of the template,
and lets any other exception through.

#### Custom options

`RenderOptions` can be extended with your own options:

```php
use Comhon\TemplateRenderer\RenderOptions;

class MyRenderOptions extends RenderOptions
{
    public function __construct(
        ?string $locale = null,
        ?string $timezone = null,
        ?string $preferredTimezone = null,
        public readonly ?string $currency = null,
    ) {
        parent::__construct($locale, $timezone, $preferredTimezone);
    }
}
```

The given options are available as is through `$context->options()`, in the replacements closure:

```php
Template::render(
    'Total: {{ total }}',
    fn (RenderContext $context) => [
        'total' => (new NumberFormatter($context->locale(), NumberFormatter::CURRENCY))
            ->formatCurrency($order->total, $context->options()->currency),
    ],
    new MyRenderOptions(locale: 'fr', currency: 'EUR'),
);
// Total: 12,50 €
```

and in drivers:

```php
// in MyDriver::render()
$options = $context->options();
if ($options instanceof MyRenderOptions) {
    // use $options->currency
}
```

Drivers that don't know these options simply ignore them.

### 2. Register the driver

The closure receives the configuration of the renderer using the driver.
In a Laravel project, call it in the `boot` method of a service provider.

```php
Template::extend('my-driver', fn (array $config) => new MyDriver($config));
```

### 3. Declare a renderer using the driver

In the configuration (the array given to `TemplateManager`, or `config/template-renderer.php` with Laravel):

```php
'renderers' => [
    'my-renderer' => [
        'driver' => 'my-driver',
        // options given to your driver
    ],
],
```

Several renderers can use the same driver with different options.
Set `default_renderer` to use your renderer by default.

### Using a renderer without the template manager

A `Comhon\TemplateRenderer\Renderer` can be created directly with its driver.
It resolves the context, applies the locale and calls the replacements closure (`Template::render()` uses it under the hood).

```php
use Comhon\TemplateRenderer\Renderer;

$renderer = new Renderer(new MyDriver($config), new MyLocaleHandler); // the locale handler is optional

$renderer->render($template, fn (RenderContext $context) => [
    'meeting' => $context->dateTime('2026-12-12 14:00'),
], new RenderOptions(locale: 'fr', timezone: 'Europe/Paris'));
```

## Upgrading

See [UPGRADING](UPGRADING.md) for upgrade instructions between major versions.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## Credits

-   [jean-philippe](https://github.com/comhon-project)

## License

The MIT License (MIT). See [License File](LICENSE.md) for more information.

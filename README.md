# PHP Template Renderer

-   Your library needs to render templates (example: email template) but you don't want to implement it ?
-   You want your template renderer to be customizable by developers that use your library ?

This library is made for that! it permit to render templates and permit to define which renderer to use. This library is generally intended for Laravel projects but you can use it even if you are not implementing a Laravel project.

There is only one renderer available by default ([twig](https://twig.symfony.com)), but you can add your own by defining your own driver.

## Installation

You can install the package via composer:

```bash
composer require comhon-project/template-renderer
```

For laravel project, you can publish the config file with:

```bash
php artisan vendor:publish --tag="template-renderer-config"
```

## Usage

### Laravel project

```php
$rendered = Template::render(
    'Hello {{ user.name }} !!!',
    ['user' => ['name' => 'john doe']]
);

echo $rendered;
// output: Hello john doe !!!
```

### Non Laravel project

```php
use Comhon\TemplateRenderer\TemplateManager;

// the instantiation mechanism should be implemented in a specific place
// and called only one time (TemplateManager should be used as singleton)
$templateManager = new TemplateManager($app);

$rendered = $templateManager->render(
    'Hello {{ user.name }} !!!',
    ['user' => ['name' => 'john doe']]
);

echo $rendered;
// output: Hello john doe !!!
```

## Adding custom renderer driver

First, you will have to define a class that implements `Comhon\TemplateRenderer\Renderers\RendererInterface`

```php
<?php
use Comhon\TemplateRenderer\Renderers\RendererInterface;

class MyTemplateRenderer implements RendererInterface
{
    public function setDefaultLocale(string $locale) {}
    public function setDefaultTimezone(string $timezone) {}
    public function validate(string $template) {}
    public function render(
        string $template,
        array $replacements,
        ?string $defaultLocale = null,
        ?string $defaultTimezone = null,
        ?string $preferredTimezone = null
    ): string {}
}
```

Then, you will have to register your driver by calling the `Template` facade's `extend` method:  
(In laravel project, you may call this function in the `boot` method of your `AppServiceProvider`)
```php
    Template::extend('my-renderer', function ($app) {
        return new MyTemplateRenderer($app);
    });
```

In laravel project, you can set your renderer as the default one. To do so, modify the `default_renderer` value in the config file.


## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

-   [jean-philippe](https://github.com/comhon-project)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

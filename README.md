# PHP Template Renderer

PHP library that permit to render templates and permit to define which renderer to use.

## Installation

You can install the package via composer:

```bash
composer require comhon-project/template-renderer
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="template-renderer-config"
```

## Usage

```php
$rendered = Template::render(
    'Hello {{ user.name }} !!!',
    ['user' => ['name' => 'doe']]
);
```

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

<?php

// config for Comhon/TemplateRenderer
return [

    /*
     | Here you may specify which of the template renderers below you wish
     | to use as your default renderer for all template rendering.
     | for more information on twig renderer, take a look at https://twig.symfony.com/
     */
    'default_renderer' => env('TEMPLATE_RENDERER', 'twig'),

    /*
     | Here you may define all of the template renderers for your application as
     | well as their drivers. Several renderers may use the same driver with
     | different options. Custom drivers are registered with Template::extend().
     */
    'renderers' => [
        'twig' => [
            'driver' => 'twig',

            /*
            | Uncomment following config if you want to use cache to speed up template loading.
            | Note: you will have to take care of clearing cache files by yourself when needed.
            */
            // 'cache' => env('TEMPLATE_RENDERER_CACHE_PATH', storage_path('template-renderer')),

            /*
            | Any other Twig option is passed as is, e.g. 'autoescape' => false for a renderer
            | dedicated to plain text (mail subjects, text mails).
            */

            /*
            | Twig sandbox, for templates written by untrusted authors (e.g. your users). A sandboxed template
            | can only use what is listed below, see https://twig.symfony.com/doc/3.x/sandbox.html
            | and the "Security" section of the README. A missing list is empty.
            |
            | The lists below hold everything that cannot run code nor reach anything outside the template:
            | no callable, no other template, no PHP constant or enum, no object dump, nothing marked as safe.
            */
            'sandbox' => [
                'enabled' => env('TEMPLATE_RENDERER_TWIG_SANDBOX', false),

                // your own Twig\Sandbox\SecurityPolicyInterface (instantiated without arguments), replaces the lists below
                // 'policy' => \App\Templates\TemplateSecurityPolicy::class,

                'allowed_tags' => ['if', 'for', 'set', 'apply', 'with', 'do', 'block', 'macro', 'types'],

                'allowed_filters' => [
                    // "escape" is applied to every printed expression by the auto escaping: keep it
                    'escape', 'e',
                    // text
                    'upper', 'lower', 'capitalize', 'title', 'trim', 'nl2br', 'striptags', 'replace', 'format', 'split', 'convert_encoding', 'url_encode',
                    // collections
                    'default', 'length', 'join', 'keys', 'merge', 'first', 'last', 'reverse', 'slice', 'batch',
                    // numbers and dates
                    'abs', 'round', 'number_format', 'date', 'date_modify', 'format_number', 'format_currency', 'format_datetime', 'format_date', 'format_time',
                    // names
                    'country_name', 'currency_name', 'currency_symbol', 'language_name', 'locale_name', 'timezone_name',
                ],

                'allowed_functions' => [
                    '__', 'trans', 'trans_choice', 'date', 'cycle', 'max', 'min', 'random',
                    'country_names', 'currency_names', 'language_names', 'locale_names', 'script_names', 'timezone_names', 'country_timezones',
                ],

                // the safe built-in tests (defined, empty, even, odd, null, same as...) are always allowed
                'allowed_tests' => [],

                // by class or interface, e.g. [DateTimeInterface::class => ['format']]
                'allowed_methods' => [
                    // __toString: printing a date, or giving a Stringable one (e.g. Carbon) to a filter
                    DateTimeInterface::class => ['__toString', 'format', 'getTimestamp'],
                ],
                'allowed_properties' => [],
            ],
        ],
    ],

];

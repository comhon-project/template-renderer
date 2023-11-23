<?php

// config for Comhon/TemplateRenderer
return [

    /*
     | Here you may specify which of the template renderers below you wish
     | to use as your default renderer for all template rendering.
     */
    'default_renderer' => env('TEMPLATE_RENDERER', 'twig'),

    /*
     | Here you may define all of the cache template engines for your application as
     | well as their drivers. Template engines are used to render templates.
     | In this library they will be used typically for notifications (email templates).
     */
    'renderers' => [
        'twig' => [
            'driver' => 'twig',
            'cache' => env('TEMPLATE_RENDERER_CACHE_PATH', storage_path('template-renderer')),
        ],
    ],

];

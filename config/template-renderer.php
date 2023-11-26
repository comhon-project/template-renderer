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
     | well as their drivers.
     */
    'renderers' => [
        'twig' => [
            'driver' => 'twig',

            /*
            | Uncomment following config if you want to use cache to speed up template loading.
            | Note: you will have to take care of clearing cache files by yourself when needed.
            */
            // 'cache' => env('TEMPLATE_RENDERER_CACHE_PATH', storage_path('template-renderer')),
        ],
    ],

];

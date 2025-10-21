<?php

return [

    'default' => 'default',

    'documentations' => [

        'default' => [

            'api' => [
                'title' => 'API Avances',
            ],

            'routes' => [
                'api' => 'api/documentation',
            ],

            'paths' => [
                'use_absolute_path' => false,
                'docs_json' => 'api-docs.json',
                'docs_yaml' => false,
                'format_to_use_for_docs' => env('L5_SWAGGER_FORMAT', 'json'),
                'base' => env('L5_SWAGGER_BASE_PATH', null),

                // 👇 ESSA PARTE É A MAIS IMPORTANTE
                'annotations' => [
                    base_path('app/Swagger'),
                    base_path('app/Http/Controllers'),
                ],
            ],
        ],
    ],

];

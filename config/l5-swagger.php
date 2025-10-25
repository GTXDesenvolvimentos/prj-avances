<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'Minha API Laravel',
                'version' => '1.0.0',
            ],
        ],
    ],
    'paths' => [
        'docs' => storage_path('api-docs'),
        'docs_json' => 'api-docs.json',
        'docs_yaml' => 'api-docs.yaml',
        'annotations' => [
            base_path('app'),
        ],
    ],
    'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', true),
    'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
    'swagger_version' => env('L5_SWAGGER_SWAGGER_VERSION', '3.0'),
    'securityDefinitions' => [
        'securitySchemes' => [
            /*
            'api_key' => [
                'type' => 'apiKey',
                'name' => 'api_key',
                'in' => 'header',
            ],
            */
        ],
    ],
];
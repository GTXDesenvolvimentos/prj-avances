<?php

return [
    'paths' => ['api/*', 'public/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://staging.avances.com.br',
        'https://avances.com.br',
        'http://localhost:3000', // pra testes locais
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];

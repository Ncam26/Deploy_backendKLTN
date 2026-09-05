<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env(
            'FRONTEND_URL',
            'https://sanminhtien.vercel.app'
        ),
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Frontend đang dùng Bearer Token nên không cần cookie chéo domain.
    'supports_credentials' => false,
];

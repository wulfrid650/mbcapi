<?php

 $allowedOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'https://madibabc.com,https://www.madibabc.com'))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_merge($allowedOrigins, [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        'http://mbc.madibabc.local',
    ])),

    'allowed_origins_patterns' => [
        '#^https?://(.*\.)?madibabc\.com$#',
        '#^https?://(.*\.)?madibabc\.local$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Length', 'Content-Range'],

    'max_age' => 86400,

    'supports_credentials' => false,

];

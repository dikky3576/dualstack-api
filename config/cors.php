<?php

/*
 * Same values as Laravel's built-in defaults, written out so one thing can be
 * added: the browser may only read response headers listed in
 * `exposed_headers` on cross-origin requests. The web app needs
 * Content-Disposition to name the downloaded template ZIP.
 */
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Hanya frontend sendiri. Webhook Midtrans memanggil server-ke-server,
    // jadi tidak terpengaruh CORS. Tambah domain lain lewat CORS_ALLOWED_ORIGINS
    // (dipisah koma) bila perlu.
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        [
            rtrim((string) env('FRONTEND_URL', 'https://dualstack.my.id'), '/'),
            'https://dualstack.my.id',
            'https://www.dualstack.my.id',
            'http://localhost:5173',
        ],
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];

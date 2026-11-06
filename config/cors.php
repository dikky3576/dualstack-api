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

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];

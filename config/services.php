<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Email pemilik yang diberi tahu saat ada permintaan proyek baru.
    // Kosong = permintaan tetap tersimpan, hanya tanpa email.
    'project_requests' => [
        'notify_email' => env('PROJECT_REQUEST_NOTIFY_EMAIL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'notification_secret' => env('MIDTRANS_SIGNATURE_SECRET_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'notification_url' => env('MIDTRANS_NOTIFICATION_URL'),
    ],

    // DOKU Checkout. Aktif bila Client ID dan Secret Key terisi.
    'doku' => [
        'client_id' => env('DOKU_CLIENT_ID'),
        'secret_key' => env('DOKU_SECRET_KEY'),
        'is_production' => env('DOKU_IS_PRODUCTION', false),
        // Opsional: path notifikasi bila server menambah/menghapus prefix.
        'notification_path' => env('DOKU_NOTIFICATION_PATH'),
    ],
];

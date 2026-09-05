<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'transactional_mail' => [
        // Railway Hobby dùng "brevo" để gửi qua HTTPS thay vì cổng SMTP.
        'driver' => env('TRANSACTIONAL_MAIL_DRIVER', 'laravel'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'sender_email' => env(
            'BREVO_SENDER_EMAIL',
            env('MAIL_FROM_ADDRESS')
        ),
        'sender_name' => env(
            'BREVO_SENDER_NAME',
            env('MAIL_FROM_NAME', 'Sân Minh Tiến')
        ),
        'endpoint' => env(
            'BREVO_API_ENDPOINT',
            'https://api.brevo.com/v3/smtp/email'
        ),
        'timeout' => (int) env('BREVO_TIMEOUT', 15),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

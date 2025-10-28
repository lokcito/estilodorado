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

    'resend' => [
        'key' => env('RESEND_KEY'),
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
    'culqi' => [
        'public_key' => env('CULQI_PUBLIC_KEY'),
        'private_key' => env('CULQI_PRIVATE_KEY'),
        'api_url' => env('CULQI_API_URL', 'https://api.culqi.com/v2'),
    ],

    'geo' => [
    'enabled'   => env('GEO_ENABLED', true),
    'base'      => env('GEO_NOMINATIM_BASE', 'https://nominatim.openstreetmap.org'),
    'email'     => env('GEO_CONTACT_EMAIL'),
    'verify'    => env('GEO_VERIFY_SSL', true),
    'timeout'   => env('GEO_TIMEOUT', 5),
    'cache_min' => env('GEO_CACHE_MIN', 1440),
    ],

];

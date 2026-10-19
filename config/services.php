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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'face' => [
        'api' => env('FACE_API', 'http://127.0.0.1:5001'),
    ],

    /*
     * The third-party geolocation lookup (config/geoip.php) used purely to
     * enrich the authentication audit log with where a sign-in came from. It
     * is best-effort: sign-in must keep working when it is slow or
     * unreachable, and it can be switched off entirely — the test suite does
     * exactly that so it never reaches for the network.
     */
    'ip_lookup' => [
        'enabled' => env('IP_LOOKUP_ENABLED', true),
    ],

];

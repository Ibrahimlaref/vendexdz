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

    //
    'zr' => [
    'api_key'   => env('ZR_API_KEY'),
    'tenant_id' => env('ZR_TENANT_ID'),
     ],
    'eco' => [
        'anderson_token'   => env('ANDERSON_API_KEY'),
        'tenant_id' => env('ZR_TENANT_ID'),
        '17track' => env('17TRACKAPIKEY')
    ],

    'google_sheets' => [
        'service_account_credentials' => env('GOOGLE_SERVICE_ACCOUNT_CREDENTIALS'),
        'service_account_email' => env('GOOGLE_SERVICE_ACCOUNT_EMAIL'),
    ],
];

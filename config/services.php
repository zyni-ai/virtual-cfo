<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mailgun
    |--------------------------------------------------------------------------
    |
    | Used for inbound email webhook signature verification and inbox domain.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'),
        'scheme' => 'https',
    ],

    /*
    |--------------------------------------------------------------------------
    | Zoho Invoice
    |--------------------------------------------------------------------------
    |
    | OAuth credentials for Zoho Invoice API integration.
    | Uses Zoho India endpoints (.in TLD) by default.
    |
    */

    'zoho' => [
        'redirect_uri' => env('ZOHO_REDIRECT_URI'),
    ],

];

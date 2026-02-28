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
        'secret' => env('MAILGUN_SECRET'),
        'inbox_domain' => env('MAILGUN_INBOX_DOMAIN', 'inbox.example.com'),
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

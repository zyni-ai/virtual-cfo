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
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'redirect_uri' => env('ZOHO_REDIRECT_URI'),
        'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.in'),
        'api_url' => env('ZOHO_API_URL', 'https://www.zohoapis.in'),
    ],

];

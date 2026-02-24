<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure API keys for AI providers used by the application.
    | The primary provider is Mistral, used for PDF parsing and head matching.
    |
    */

    'providers' => [

        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY'),
        ],

    ],

];

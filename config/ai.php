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
            'driver' => 'mistral',
            'api_key' => env('MISTRAL_API_KEY'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | AI Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which models each AI agent uses. This allows switching models
    | via environment variables without code changes.
    |
    */

    'models' => [

        'parsing' => env('AI_PARSING_MODEL', 'mistral-large-latest'),

        'matching' => env('AI_MATCHING_MODEL', 'mistral-large-latest'),

    ],

];

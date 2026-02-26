<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure API keys for AI providers used by the application.
    | OpenRouter is the primary provider for all AI agents (parsing, matching).
    | Mistral is retained for OCR only (Mistral's /v1/ocr is not on OpenRouter).
    |
    */

    'providers' => [

        'openrouter' => [
            'driver' => 'openai',
            'key' => env('OPENROUTER_API_KEY'),
            'url' => 'https://openrouter.ai/api/v1',
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
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

        'parsing' => env('AI_PARSING_MODEL', 'mistralai/mistral-large-latest'),

        'matching' => env('AI_MATCHING_MODEL', 'mistralai/mistral-large-latest'),

        'ocr' => env('AI_OCR_MODEL', 'mistral-ocr-latest'),

    ],

];

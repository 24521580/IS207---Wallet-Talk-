<?php

return [
    /*
    | Demo AI Mode returns deterministic structured JSON for known Vietnamese
    | examples so the product can be demonstrated without an external API key.
    */
    'demo_mode' => env('DEMO_AI_MODE', true),

    /*
    | gemini | openai
    | Used only when DEMO_AI_MODE=false.
    */
    'provider' => env('AI_PROVIDER', 'gemini'),

    'timeout' => (int) env('AI_TIMEOUT', 20),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
    ],
];

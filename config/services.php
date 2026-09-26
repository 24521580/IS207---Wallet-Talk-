<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    /*
    |--------------------------------------------------------------------------
    | AI Service Credentials (Ví Nói)
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'demo_mode' => filter_var(env('DEMO_AI_MODE', false), FILTER_VALIDATE_BOOLEAN),
        'provider' => strtolower((string) env('AI_PROVIDER', 'gemini')),
        'timeout' => (int) env('AI_TIMEOUT', 25),

        'openai' => [
            'api_key' => ($openaiKey = trim((string) env('OPENAI_API_KEY'), " \t\n\r\0\x0B\"'")) !== '' ? $openaiKey : null,
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        ],

        'gemini' => [
            'api_key' => ($geminiKey = trim((string) env('GEMINI_API_KEY', env('GOOGLE_API_KEY', env('GOOGLE_GENERATIVE_AI_API_KEY'))), " \t\n\r\0\x0B\"'")) !== '' ? $geminiKey : null,
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
        ],

        'groq' => [
            'api_key' => ($groqKey = trim((string) env('GROQ_API_KEY'), " \t\n\r\0\x0B\"'")) !== '' ? $groqKey : null,
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'endpoint' => env('GROQ_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions'),
        ],
    ],

];

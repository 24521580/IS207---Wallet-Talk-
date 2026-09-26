<?php

$cleanKey = static function (mixed $value): ?string {
    if ($value === null) {
        return null;
    }

    $trimmed = trim((string) $value, " \t\n\r\0\x0B\"'");

    return $trimmed === '' ? null : $trimmed;
};

$fallbackModels = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-2.5-flash,gemini-2.0-flash,gemini-flash-latest,gemini-1.5-flash'))
)));

return [
    /*
    | Demo AI Mode returns deterministic structured JSON for known Vietnamese
    | examples so the product can be demonstrated without an external API key.
    */
    'demo_mode' => filter_var(env('DEMO_AI_MODE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | gemini | openai | groq
    | Used only when DEMO_AI_MODE=false.
    */
    'provider' => strtolower((string) env('AI_PROVIDER', 'gemini')),

    'timeout' => (int) env('AI_TIMEOUT', 25),

    'openai' => [
        'api_key' => $cleanKey(env('OPENAI_API_KEY')),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
    ],

    'groq' => [
        'api_key' => $cleanKey(env('GROQ_API_KEY')),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'endpoint' => env('GROQ_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions'),
    ],

    'gemini' => [
        'api_key' => $cleanKey(env('GEMINI_API_KEY', env('GOOGLE_API_KEY', env('GOOGLE_GENERATIVE_AI_API_KEY')))),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
        'fallback_models' => $fallbackModels,
    ],
];

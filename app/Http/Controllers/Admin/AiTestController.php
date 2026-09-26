<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\LiveAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiTestController extends Controller
{
    public function __construct(private readonly LiveAiClient $client) {}

    /**
     * Safely test AI connectivity for administrators / developers.
     * Never returns keys or secrets.
     */
    public function test(Request $request): JsonResponse
    {
        $provider = $request->query('provider');
        $result = $this->client->testConnection($provider ? (string) $provider : null);

        $status = ($result['connection'] === 'ok') ? 200 : 502;

        return response()->json($result, $status);
    }

    /**
     * Test an actual parse request with a minimal sample sentence.
     * Use this to confirm the AI key + model is working end-to-end.
     * Never persists data.
     */
    public function testParse(Request $request): JsonResponse
    {
        $provider = $request->query('provider');
        $result = $this->client->testParse($provider ? (string) $provider : null);

        $status = $result['ok'] ? 200 : 502;

        return response()->json($result, $status);
    }

    /**
     * Debug endpoint to show detailed AI configuration without exposing secrets.
     */
    public function debug(Request $request): JsonResponse
    {
        $provider = $this->client->resolveProvider();
        $hasKey = $this->client->hasKeyFor($provider);
        $model = $this->client->getModel($provider);

        $debugInfo = [
            'provider' => $provider,
            'model' => $model,
            'has_api_key' => $hasKey,
            'key_length' => $hasKey ? strlen($this->client->apiKey($provider) ?? '') : 0,
            'key_prefix' => $hasKey ? substr($this->client->apiKey($provider) ?? '', 0, 8) . '...' : null,
            'demo_mode' => config('ai.demo_mode'),
            'timeout' => config('ai.timeout'),
            'env_variables' => [
                'AI_PROVIDER' => env('AI_PROVIDER'),
                'GEMINI_API_KEY_set' => !empty(env('GEMINI_API_KEY')),
                'OPENAI_API_KEY_set' => !empty(env('OPENAI_API_KEY')),
                'GROQ_API_KEY_set' => !empty(env('GROQ_API_KEY')),
                'DEMO_AI_MODE' => env('DEMO_AI_MODE'),
            ],
            'config_ai' => [
                'provider' => config('ai.provider'),
                'demo_mode' => config('ai.demo_mode'),
                'gemini.api_key_set' => !empty(config('ai.gemini.api_key')),
                'openai.api_key_set' => !empty(config('ai.openai.api_key')),
                'groq.api_key_set' => !empty(config('ai.groq.api_key')),
            ],
        ];

        return response()->json($debugInfo);
    }
}

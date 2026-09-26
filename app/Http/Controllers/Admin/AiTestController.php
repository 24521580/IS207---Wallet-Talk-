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
}

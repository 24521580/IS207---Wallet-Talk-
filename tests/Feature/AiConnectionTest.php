<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_ai_test_endpoint(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($user)
            ->getJson(route('admin.ai.test'))
            ->assertForbidden();
    }

    public function test_admin_can_access_ai_test_endpoint_and_reports_missing_key(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.api_key' => '',
            'ai.openai.api_key' => '',
            'ai.groq.api_key' => '',
            'services.ai.gemini.api_key' => '',
            'services.ai.openai.api_key' => '',
            'services.ai.groq.api_key' => '',
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.ai.test'))
            ->assertStatus(502);

        $response->assertJsonPath('configured', false);
        $response->assertJsonPath('connection', 'failed');
        $response->assertJsonPath('error_type', 'missing_key');
        // Absolute verification: API key must NOT be returned in response!
        $this->assertArrayNotHasKey('api_key', $response->json());
    }

    public function test_admin_ai_test_success_response(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.api_key' => 'dummy-valid-key',
        ]);

        Http::fake(['*' => Http::response([
            'models' => [
                ['name' => 'models/gemini-2.0-flash'],
            ],
        ], 200)]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.ai.test'))
            ->assertOk();

        $response->assertJsonPath('configured', true);
        $response->assertJsonPath('connection', 'ok');
        $response->assertJsonPath('provider', 'gemini');
        $response->assertJsonPath('http_status', 200);
        $this->assertArrayNotHasKey('api_key', $response->json());
    }
}

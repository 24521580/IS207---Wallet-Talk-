<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for Live AI, error reporting, and Demo AI Mode boundaries.
 * Verifies that errors are never quietly masked with demo data when DEMO_AI_MODE=false.
 */
class AiFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_live_api_failure_returns_error_and_does_not_mask_with_demo(): void
    {
        config([
            'ai.demo_mode' => false,
            'ai.provider' => 'gemini',
            'ai.gemini.api_key' => 'test-key',
        ]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'API quota exceeded']], 500)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('transactions.parse'), ['text' => 'Hôm nay ăn sáng 30k'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_ai_timeout_returns_error_response(): void
    {
        config([
            'ai.demo_mode' => false,
            'ai.provider' => 'gemini',
            'ai.gemini.api_key' => 'test-key',
        ]);
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->actingAs(User::factory()->create())
            ->postJson(route('transactions.parse'), ['text' => 'Hôm nay ăn sáng 30k'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error_type', 'timeout');
    }

    public function test_demo_mode_returns_deterministic_response_when_explicitly_enabled(): void
    {
        config([
            'ai.demo_mode' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('transactions.parse'), ['text' => 'Hôm nay ăn sáng 30k'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.demo', true)
            ->assertJsonPath('data.transactions.0.amount', 30000);
    }

    public function test_live_ai_json_is_used_when_available(): void
    {
        config([
            'ai.demo_mode' => false,
            'ai.provider' => 'gemini',
            'ai.gemini.api_key' => 'test-key',
        ]);
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [[
                'text' => json_encode([
                    'transactions' => [[
                        'type' => 'expense',
                        'amount' => 45000,
                        'category' => 'Ăn uống',
                        'date' => now()->toDateString(),
                        'note' => 'Cà phê',
                    ]],
                    'unresolved' => [],
                ], JSON_UNESCAPED_UNICODE),
            ]]]]],
        ])]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('transactions.parse'), ['text' => 'cà phê 45k'])
            ->assertOk()
            ->assertJsonPath('data.demo', false)
            ->assertJsonPath('data.provider', 'gemini')
            ->assertJsonPath('data.transactions.0.amount', 45000);
    }

    public function test_unknown_category_falls_back_to_an_existing_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('transactions.parse'), [
            'text' => 'mua đồ linh tinh 100k',
        ]);

        $response->assertOk();

        $type = $response->json('data.transactions.0.type');
        $category = Category::query()->find($response->json('data.transactions.0.category_id'));

        $this->assertNotNull($category, 'AI phải luôn map về một danh mục có thật trong database.');
        $this->assertSame($type, $category->type);
        $this->assertContains($category->name, ['Khác', 'Mua sắm']);
    }
}

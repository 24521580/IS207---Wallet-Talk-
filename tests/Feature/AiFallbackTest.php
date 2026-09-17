<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Case 7 – AI API lỗi/timeout vẫn phải có kết quả dùng được (fallback demo)
 * và không bao giờ lưu thẳng vào database.
 */
class AiFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_live_api_failure_falls_back_to_demo_parser(): void
    {
        config([
            'ai.demo_mode' => false,
            'ai.provider' => 'gemini',
            'ai.gemini.api_key' => 'test-key',
        ]);
        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('transactions.parse'), ['text' => 'Hôm nay ăn sáng 30k'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.demo', true)
            ->assertJsonPath('data.transactions.0.amount', 30000);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_invalid_ai_json_falls_back_to_demo_parser(): void
    {
        config([
            'ai.demo_mode' => false,
            'ai.provider' => 'gemini',
            'ai.gemini.api_key' => 'test-key',
        ]);
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'xin chào, tôi là AI']]]]],
        ])]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('transactions.parse'), ['text' => 'Hôm nay ăn sáng 30k'])
            ->assertOk()
            ->assertJsonPath('data.demo', true)
            ->assertJsonCount(1, 'data.transactions');
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

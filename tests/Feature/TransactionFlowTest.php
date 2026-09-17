<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@vinoi.com',
            'password' => 'Demo123@',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Demo123@',
        ])->assertRedirect('/dashboard');
    }

    public function test_empty_ai_input_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('transactions.parse'), ['text' => ''])
            ->assertStatus(422);
    }

    public function test_ai_parse_returns_structured_json_without_saving(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('transactions.parse'), [
                'text' => 'Hôm nay ăn sáng 30k, đổ xăng 100k, chiều mua sách 150k',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.demo', true)
            ->assertJsonCount(3, 'data.transactions');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_confirm_saves_reviewed_transactions(): void
    {
        $user = User::factory()->create();
        $food = Category::query()->where('name', 'Ăn uống')->first();

        $this->actingAs($user)
            ->post(route('transactions.confirm'), [
                'transactions' => [[
                    'note' => 'Ăn sáng',
                    'amount' => 30000,
                    'type' => 'expense',
                    'category_id' => $food->id,
                    'transaction_date' => now()->toDateString(),
                    'source' => 'ai',
                ]],
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 30000,
            'note' => 'Ăn sáng',
        ]);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $food = Category::query()->where('name', 'Ăn uống')->first();

        $this->actingAs($user)
            ->from(route('transactions.create'))
            ->post(route('transactions.confirm'), [
                'transactions' => [[
                    'note' => 'Sai',
                    'amount' => -1000,
                    'type' => 'expense',
                    'category_id' => $food->id,
                    'transaction_date' => now()->toDateString(),
                    'source' => 'manual',
                ]],
            ])
            ->assertSessionHasErrors();
    }

    public function test_user_cannot_edit_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $category = Category::query()->where('name', 'Ăn uống')->first();
        $transaction = Transaction::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => 'expense',
        ]);

        $this->actingAs($intruder)
            ->get(route('transactions.edit', $transaction))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_open_admin_area(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($user)
            ->get(route('admin.categories.index'))
            ->assertForbidden();
    }
}

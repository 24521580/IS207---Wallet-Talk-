<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $food;

    private Category $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);

        $this->user = User::factory()->create();
        $this->food = Category::query()->where('name', 'Ăn uống')->first();
        $this->transport = Category::query()->where('name', 'Di chuyển')->first();
    }

    public function test_history_only_lists_own_transactions(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->food->id,
            'type' => 'expense',
            'note' => 'Của tôi',
        ]);
        Transaction::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => $this->food->id,
            'type' => 'expense',
            'note' => 'Ca người khác',
        ]);

        $this->actingAs($this->user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Của tôi')
            ->assertDontSee('Của người khác');
    }

    public function test_search_matches_note_and_category_name(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->transport->id,
            'type' => 'expense',
            'note' => 'Đổ xăng',
        ]);
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->food->id,
            'type' => 'expense',
            'note' => 'Ăn trưa',
        ]);

        $this->actingAs($this->user)
            ->get(route('transactions.index', ['q' => 'xăng']))
            ->assertOk()
            ->assertSee('Đổ xăng')
            ->assertDontSee('Ăn trưa');
    }

    public function test_filters_by_type_category_and_date_range(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->food->id,
            'type' => 'expense',
            'transaction_date' => now()->subDays(30)->toDateString(),
            'note' => 'Chi cũ',
        ]);
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => Category::query()->where('name', 'Lương')->first()->id,
            'type' => 'income',
            'transaction_date' => now()->toDateString(),
            'note' => 'Lương tháng này',
        ]);

        // Lọc theo loại thu trong tháng hiện tại → chỉ còn giao dịch thu.
        $this->actingAs($this->user)
            ->get(route('transactions.index', [
                'type' => 'income',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Lương tháng này')
            ->assertDontSee('Chi cũ');

        // Lọc theo danh mục Ăn uống → chỉ còn khoản chi cũ.
        $this->actingAs($this->user)
            ->get(route('transactions.index', ['category_id' => $this->food->id]))
            ->assertOk()
            ->assertSee('Chi cũ')
            ->assertDontSee('Lương tháng này');
    }

    public function test_empty_state_is_shown_when_user_has_no_transaction(): void
    {
        $this->actingAs($this->user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Ví của bạn đang trống.');
    }

    public function test_user_can_update_own_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->food->id,
            'type' => 'expense',
            'amount' => 30000,
        ]);

        $this->actingAs($this->user)
            ->put(route('transactions.update', $transaction), [
                'note' => 'Sửa lại nội dung',
                'amount' => 55000,
                'type' => 'expense',
                'category_id' => $this->food->id,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'note' => 'Sửa lại nội dung',
            'amount' => 55000,
        ]);
    }

    public function test_category_type_must_match_transaction_type(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->food->id,
            'type' => 'expense',
        ]);

        // Gửi danh mục chi nhưng loại là thu → service phải từ chối.
        $this->actingAs($this->user)
            ->from(route('transactions.edit', $transaction))
            ->put(route('transactions.update', $transaction), [
                'note' => 'Sai loại',
                'amount' => 10000,
                'type' => 'income',
                'category_id' => $this->food->id,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_user_cannot_delete_another_users_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => $this->food->id,
            'type' => 'expense',
        ]);

        $this->actingAs($this->user)
            ->delete(route('transactions.destroy', $transaction))
            ->assertForbidden();

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_user_can_delete_own_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->food->id,
            'type' => 'expense',
        ]);

        $this->actingAs($this->user)
            ->delete(route('transactions.destroy', $transaction))
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }
}

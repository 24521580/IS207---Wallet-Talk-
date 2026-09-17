<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_admin_can_view_category_management_page_with_stats(): void
    {
        $admin = User::factory()->admin()->create();
        Transaction::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::query()->where('name', 'Ăn uống')->first()->id,
            'type' => 'expense',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Admin')
            ->assertSee('Giao dịch');
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.categories.store'), [
                'name' => 'Du lịch',
                'type' => 'expense',
                'icon' => '✈',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', ['name' => 'Du lịch', 'type' => 'expense']);
    }

    public function test_category_name_must_be_unique_per_type(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.categories.store'), [
                'name' => 'Ăn uống',
                'type' => 'expense',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_cannot_delete_category_used_by_transactions(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->where('name', 'Ăn uống')->first();
        Transaction::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => $category->id,
            'type' => 'expense',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_admin_can_delete_unused_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->create(['name' => 'Danh mục tạm', 'type' => 'expense']);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_non_admin_cannot_manage_categories(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($user)
            ->post(route('admin.categories.store'), ['name' => 'Hack', 'type' => 'expense'])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.categories.index'))
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->user = User::factory()->create();
    }

    public function test_dashboard_summary_matches_database(): void
    {
        $this->transaction('expense', 'Ăn uống', 100000, now());
        $this->transaction('expense', 'Di chuyển', 50000, now());
        $this->transaction('income', 'Lương', 20000000, now()->startOfMonth());
        // Giao dịch tháng trước không được tính vào tháng này.
        $this->transaction('expense', 'Giải trí', 999999, now()->subMonth());

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk()
            ->assertViewHas('income', 20000000)
            ->assertViewHas('expense', 150000)
            ->assertViewHas('balance', 19850000)
            ->assertViewHas('today_expense', 150000);
    }

    public function test_dashboard_category_chart_groups_expense_of_current_month(): void
    {
        $this->transaction('expense', 'Ăn uống', 60000, now());
        $this->transaction('expense', 'Ăn uống', 40000, now());
        $this->transaction('expense', 'Di chuyển', 50000, now());

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $chart = $response->viewData('category_chart');
        $this->assertSame(['Ăn uống', 'Di chuyển'], $chart['labels']);
        $this->assertSame([100000, 50000], $chart['values']);
    }

    public function test_report_month_preset_returns_current_month_totals(): void
    {
        $this->transaction('expense', 'Mua sắm', 300000, now()->startOfMonth());
        $this->transaction('income', 'Thưởng', 500000, now());

        $response = $this->actingAs($this->user)->get(route('reports.index', ['preset' => 'month']));

        $response->assertOk()
            ->assertViewHas('preset', 'month')
            ->assertViewHas('income', 500000)
            ->assertViewHas('expense', 300000);
    }

    public function test_report_custom_range_filters_by_given_dates(): void
    {
        $this->transaction('expense', 'Giáo dục', 150000, now()->subDays(10));
        $this->transaction('expense', 'Giáo dục', 250000, now()->subDays(100));

        $response = $this->actingAs($this->user)->get(route('reports.index', [
            'preset' => 'custom',
            'from' => now()->subDays(20)->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertViewHas('expense', 150000)
            ->assertViewHas('from', now()->subDays(20)->toDateString())
            ->assertViewHas('to', now()->toDateString());
    }

    public function test_report_percentage_is_relative_to_total_expense(): void
    {
        $this->transaction('expense', 'Ăn uống', 75000, now());
        $this->transaction('expense', 'Hóa đơn', 25000, now());

        $response = $this->actingAs($this->user)->get(route('reports.index', ['preset' => 'month']));
        $rows = $response->viewData('top_categories')->keyBy('category');

        $this->assertSame(75.0, $rows['Ăn uống']['percentage']);
        $this->assertSame(25.0, $rows['Hóa đơn']['percentage']);
    }

    public function test_report_does_not_include_other_users_expense(): void
    {
        $this->transaction('expense', 'Ăn uống', 100000, now());

        $other = User::factory()->create();
        Transaction::factory()->create([
            'user_id' => $other->id,
            'category_id' => Category::query()->where('name', 'Ăn uống')->first()->id,
            'type' => 'expense',
            'amount' => 5000000,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get(route('reports.index', ['preset' => 'month']))
            ->assertOk()
            ->assertViewHas('expense', 100000);
    }

    private function transaction(string $type, string $categoryName, int $amount, \DateTimeInterface $date): Transaction
    {
        return Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => Category::query()->where('name', $categoryName)->first()->id,
            'type' => $type,
            'amount' => $amount,
            'transaction_date' => $date->format('Y-m-d'),
        ]);
    }
}

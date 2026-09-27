<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    // ── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * Tạo hoặc cập nhật ngân sách (upsert theo user+category+month+year).
     */
    public function upsert(User $user, int $categoryId, int $month, int $year, int $limitAmount): Budget
    {
        /** @var Budget $budget */
        $budget = Budget::query()->updateOrCreate(
            [
                'user_id'     => $user->id,
                'category_id' => $categoryId,
                'month'       => $month,
                'year'        => $year,
            ],
            ['limit_amount' => $limitAmount],
        );

        return $budget;
    }

    public function delete(Budget $budget): void
    {
        $budget->delete();
    }

    // ── Query ─────────────────────────────────────────────────────────────────

    /**
     * Lấy tất cả budgets của user trong tháng/năm, kèm category và spending đã tính sẵn.
     *
     * @return Collection<int, array{
     *     budget: Budget,
     *     category: Category,
     *     limit_amount: int,
     *     current_spending: int,
     *     remaining: int,
     *     over_budget: bool,
     *     percent_used: float,
     * }>
     */
    public function forUserMonth(User $user, int $month, int $year): Collection
    {
        $budgets = Budget::query()
            ->with('category')
            ->where('user_id', $user->id)
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('id')
            ->get();

        // Tính spending cho tất cả categories trong một query
        $categoryIds = $budgets->pluck('category_id')->all();
        $spendingMap = $this->spendingMap($user, $categoryIds, $month, $year);

        return $budgets->map(function (Budget $budget) use ($spendingMap): array {
            $spent      = $spendingMap[$budget->category_id] ?? 0;
            $limit      = $budget->limit_amount;
            $remaining  = max(0, $limit - $spent);
            $percent    = $limit > 0 ? round(min(($spent / $limit) * 100, 100), 1) : 0.0;

            return [
                'budget'           => $budget,
                'category'         => $budget->category,
                'limit_amount'     => $limit,
                'current_spending' => $spent,
                'remaining'        => $remaining,
                'over_budget'      => $spent > $limit,
                'percent_used'     => $percent,
            ];
        });
    }

    /**
     * Tính tổng chi tiêu hiện tại của một category trong tháng/năm cho user.
     */
    public function currentSpending(User $user, int $categoryId, int $month, int $year): int
    {
        return (int) Transaction::query()
            ->where('user_id', $user->id)
            ->where('category_id', $categoryId)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->sum('amount');
    }

    /**
     * Kiểm tra budget cho nhiều transactions cùng lúc (dùng trong AI parse preview).
     *
     * Nhận vào danh sách transactions (mỗi item có category_id, amount, transaction_date, type).
     * Trả về map category_id → warning data (chỉ những category có budget VÀ sẽ vượt hạn mức).
     *
     * @param  array<int, array{category_id: int, amount: int, transaction_date: string, type: string}>  $transactions
     * @return array<int, array{
     *     category_name: string,
     *     limit_amount: int,
     *     current_spending: int,
     *     added_amount: int,
     *     projected_spending: int,
     *     over_amount: int,
     *     over_budget: bool,
     *     month: int,
     *     year: int,
     * }>
     */
    public function checkBudgets(User $user, array $transactions): array
    {
        // Gom expense transactions, bỏ qua income
        $expenseItems = array_filter(
            $transactions,
            fn (array $t) => ($t['type'] ?? '') === Transaction::TYPE_EXPENSE
                          && isset($t['category_id'])
                          && isset($t['amount']),
        );

        if (empty($expenseItems)) {
            return [];
        }

        // Gom amount theo category_id + month/year (một batch parse có thể gộp nhiều giao dịch cùng category)
        /** @var array<string, array{category_id: int, total_amount: int, month: int, year: int}> */
        $grouped = [];
        foreach ($expenseItems as $t) {
            $date  = \Carbon\Carbon::parse($t['transaction_date']);
            $month = (int) $date->format('n');
            $year  = (int) $date->format('Y');
            $key   = "{$t['category_id']}-{$month}-{$year}";

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'category_id'  => (int) $t['category_id'],
                    'total_amount' => 0,
                    'month'        => $month,
                    'year'         => $year,
                ];
            }
            $grouped[$key]['total_amount'] += (int) $t['amount'];
        }

        $warnings = [];

        foreach ($grouped as $key => $group) {
            $categoryId = $group['category_id'];
            $month      = $group['month'];
            $year       = $group['year'];
            $addedAmt   = $group['total_amount'];

            // Tìm budget — nếu không có thì bỏ qua, không hiện cảnh báo
            $budget = Budget::query()
                ->where('user_id', $user->id)
                ->where('category_id', $categoryId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (! $budget) {
                continue;
            }

            $currentSpending  = $this->currentSpending($user, $categoryId, $month, $year);
            $projected        = $currentSpending + $addedAmt;
            $limit            = $budget->limit_amount;
            $overBudget       = $projected > $limit;

            // Luôn trả về thông tin (cả khi chưa vượt) để UI có thể hiện "còn lại"
            $warnings[$categoryId] = [
                'category_name'     => $budget->category->name ?? '',
                'limit_amount'      => $limit,
                'current_spending'  => $currentSpending,
                'added_amount'      => $addedAmt,
                'projected_spending'=> $projected,
                'over_amount'       => max(0, $projected - $limit),
                'over_budget'       => $overBudget,
                'month'             => $month,
                'year'              => $year,
            ];
        }

        return $warnings;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Một query tính spending cho nhiều categories cùng lúc → map[category_id => amount].
     *
     * @param  int[]  $categoryIds
     * @return array<int, int>
     */
    private function spendingMap(User $user, array $categoryIds, int $month, int $year): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        return Transaction::query()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->where('user_id', $user->id)
            ->whereIn('category_id', $categoryIds)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?Carbon $now = null): array
    {
        $now ??= now();
        $from = $now->copy()->startOfMonth()->toDateString();
        $to = $now->copy()->endOfMonth()->toDateString();
        $today = $now->toDateString();

        $monthRows = Transaction::query()
            ->with('category')
            ->ownedBy($user)
            ->whereBetween('transaction_date', [$from, $to])
            ->get();

        $income = (int) $monthRows->where('type', Transaction::TYPE_INCOME)->sum('amount');
        $expense = (int) $monthRows->where('type', Transaction::TYPE_EXPENSE)->sum('amount');
        $todayExpense = (int) $monthRows
            ->where('type', Transaction::TYPE_EXPENSE)
            ->filter(fn ($row) => $row->transaction_date->toDateString() === $today)
            ->sum('amount');

        $byCategory = $monthRows
            ->where('type', Transaction::TYPE_EXPENSE)
            ->groupBy('category_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'label' => $first?->category?->name ?? 'Khác',
                    'total' => (int) $group->sum('amount'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $days = CarbonPeriod::create($now->copy()->startOfMonth(), $now->copy()->endOfMonth());
        $expenseByDay = $monthRows->where('type', Transaction::TYPE_EXPENSE)->groupBy(
            fn (Transaction $row) => $row->transaction_date->toDateString()
        );

        $lineLabels = [];
        $lineValues = [];
        foreach ($days as $day) {
            $key = $day->toDateString();
            $lineLabels[] = $day->format('d/m');
            $lineValues[] = (int) optional($expenseByDay->get($key))->sum('amount');
        }

        $recent = Transaction::query()
            ->with('category')
            ->ownedBy($user)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'today_expense' => $todayExpense,
            'category_chart' => [
                'labels' => $byCategory->pluck('label')->all(),
                'values' => $byCategory->pluck('total')->all(),
            ],
            'daily_chart' => [
                'labels' => $lineLabels,
                'values' => $lineValues,
            ],
            'recent' => $recent,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * @return array{from: string, to: string}
     */
    public function resolveRange(?string $preset, ?string $from, ?string $to): array
    {
        $today = now();

        return match ($preset) {
            'week' => [
                'from' => $today->copy()->startOfWeek()->toDateString(),
                'to' => $today->copy()->endOfWeek()->toDateString(),
            ],
            'quarter' => [
                'from' => $today->copy()->subMonths(2)->startOfMonth()->toDateString(),
                'to' => $today->copy()->endOfMonth()->toDateString(),
            ],
            'custom' => [
                'from' => $from ?: $today->copy()->startOfMonth()->toDateString(),
                'to' => $to ?: $today->toDateString(),
            ],
            default => [
                'from' => $today->copy()->startOfMonth()->toDateString(),
                'to' => $today->copy()->endOfMonth()->toDateString(),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, string $from, string $to): array
    {
        $rows = Transaction::query()
            ->with('category')
            ->ownedBy($user)
            ->whereBetween('transaction_date', [$from, $to])
            ->get();

        $income = (int) $rows->where('type', Transaction::TYPE_INCOME)->sum('amount');
        $expense = (int) $rows->where('type', Transaction::TYPE_EXPENSE)->sum('amount');

        $byCategory = $rows
            ->where('type', Transaction::TYPE_EXPENSE)
            ->groupBy('category_id')
            ->map(function (Collection $group) use ($expense) {
                $total = (int) $group->sum('amount');

                return [
                    'category' => $group->first()?->category?->name ?? 'Khác',
                    'total' => $total,
                    'percentage' => $expense > 0 ? round($total / $expense * 100, 1) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        $period = CarbonPeriod::create(Carbon::parse($from), Carbon::parse($to));
        $grouped = $rows->groupBy(fn (Transaction $row) => $row->transaction_date->toDateString());
        $labels = [];
        $expenseSeries = [];
        $incomeSeries = [];

        foreach ($period as $day) {
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $dayRows = $grouped->get($key, collect());
            $expenseSeries[] = (int) $dayRows->where('type', Transaction::TYPE_EXPENSE)->sum('amount');
            $incomeSeries[] = (int) $dayRows->where('type', Transaction::TYPE_INCOME)->sum('amount');
        }

        return [
            'from' => $from,
            'to' => $to,
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'top_categories' => $byCategory,
            'category_chart' => [
                'labels' => $byCategory->pluck('category')->all(),
                'values' => $byCategory->pluck('total')->all(),
            ],
            'daily_chart' => [
                'labels' => $labels,
                'expense' => $expenseSeries,
                'income' => $incomeSeries,
            ],
            'income_vs_expense' => [
                'labels' => ['Thu', 'Chi'],
                'values' => [$income, $expense],
            ],
        ];
    }
}

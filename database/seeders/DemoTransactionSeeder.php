<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'demo@vinoi.com')->first();
        if (! $user || $user->transactions()->exists()) {
            return;
        }

        $categories = Category::query()->get()->keyBy('name');
        $today = Carbon::now();

        $rows = [
            [$today->copy()->subDays(16), 'Lương tháng 8', 'Lương', Transaction::TYPE_INCOME, 18000000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(15), 'Thưởng dự án', 'Thưởng', Transaction::TYPE_INCOME, 1500000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(14), 'Ăn phở', 'Ăn uống', Transaction::TYPE_EXPENSE, 55000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(14), 'Grab đi làm', 'Di chuyển', Transaction::TYPE_EXPENSE, 42000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(13), 'Mua áo thun', 'Mua sắm', Transaction::TYPE_EXPENSE, 249000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(12), 'Cà phê sáng', 'Ăn uống', Transaction::TYPE_EXPENSE, 45000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(12), 'Đổ xăng', 'Di chuyển', Transaction::TYPE_EXPENSE, 120000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(11), 'Netflix', 'Giải trí', Transaction::TYPE_EXPENSE, 89000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(10), 'Mua sách Laravel', 'Giáo dục', Transaction::TYPE_EXPENSE, 180000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(9), 'Cơm văn phòng', 'Ăn uống', Transaction::TYPE_EXPENSE, 40000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(9), 'Thuốc cảm', 'Sức khỏe', Transaction::TYPE_EXPENSE, 85000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(8), 'Tiền điện', 'Hóa đơn', Transaction::TYPE_EXPENSE, 620000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(7), 'Trà sữa', 'Ăn uống', Transaction::TYPE_EXPENSE, 39000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(7), 'Gửi xe', 'Di chuyển', Transaction::TYPE_EXPENSE, 10000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(6), 'Xem phim', 'Giải trí', Transaction::TYPE_EXPENSE, 120000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(6), 'Bánh mì', 'Ăn uống', Transaction::TYPE_EXPENSE, 25000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(5), 'Internet FPT', 'Hóa đơn', Transaction::TYPE_EXPENSE, 220000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(5), 'Siêu thị', 'Mua sắm', Transaction::TYPE_EXPENSE, 310000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(4), 'Khóa học tiếng Anh', 'Giáo dục', Transaction::TYPE_EXPENSE, 499000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(4), 'Cơm tối', 'Ăn uống', Transaction::TYPE_EXPENSE, 70000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(3), 'Grab về nhà', 'Di chuyển', Transaction::TYPE_EXPENSE, 58000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(3), 'Bán đồ cũ', 'Thu nhập khác', Transaction::TYPE_INCOME, 250000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(2), 'Khám răng', 'Sức khỏe', Transaction::TYPE_EXPENSE, 350000, Transaction::SOURCE_MANUAL],
            [$today->copy()->subDays(2), 'Cà phê hẹn bạn', 'Ăn uống', Transaction::TYPE_EXPENSE, 68000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(1), 'Mua tai nghe', 'Mua sắm', Transaction::TYPE_EXPENSE, 450000, Transaction::SOURCE_AI],
            [$today->copy()->subDays(1), 'Ăn tối', 'Ăn uống', Transaction::TYPE_EXPENSE, 95000, Transaction::SOURCE_AI],
            [$today->copy(), 'Cà phê sáng', 'Ăn uống', Transaction::TYPE_EXPENSE, 42000, Transaction::SOURCE_AI],
            [$today->copy(), 'Xe bus', 'Di chuyển', Transaction::TYPE_EXPENSE, 8000, Transaction::SOURCE_MANUAL],
        ];

        foreach ($rows as [$date, $note, $categoryName, $type, $amount, $source]) {
            $category = $categories->get($categoryName);
            if (! $category) {
                continue;
            }

            Transaction::query()->create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'type' => $type,
                'amount' => $amount,
                'transaction_date' => $date->toDateString(),
                'note' => $note,
                'source' => $source,
            ]);
        }
    }
}

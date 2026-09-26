<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Ăn uống', 'type' => Category::TYPE_EXPENSE, 'icon' => '🍜'],
            ['name' => 'Di chuyển', 'type' => Category::TYPE_EXPENSE, 'icon' => '🛵'],
            ['name' => 'Mua sắm', 'type' => Category::TYPE_EXPENSE, 'icon' => '🛍️'],
            ['name' => 'Giáo dục', 'type' => Category::TYPE_EXPENSE, 'icon' => '📚'],
            ['name' => 'Hóa đơn', 'type' => Category::TYPE_EXPENSE, 'icon' => '📄'],
            ['name' => 'Giải trí', 'type' => Category::TYPE_EXPENSE, 'icon' => '🎬'],
            ['name' => 'Sức khỏe', 'type' => Category::TYPE_EXPENSE, 'icon' => '💊'],
            ['name' => 'Khác', 'type' => Category::TYPE_EXPENSE, 'icon' => '✨'],
            ['name' => 'Lương', 'type' => Category::TYPE_INCOME, 'icon' => '💼'],
            ['name' => 'Thưởng', 'type' => Category::TYPE_INCOME, 'icon' => '🎉'],
            ['name' => 'Thu nhập khác', 'type' => Category::TYPE_INCOME, 'icon' => '💰'],
        ];

        foreach ($items as $item) {
            Category::query()->updateOrCreate(
                ['name' => $item['name'], 'type' => $item['type']],
                ['icon' => $item['icon']],
            );
        }
    }
}

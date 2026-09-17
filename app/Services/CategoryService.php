<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        if ($category->transactions()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Không thể xóa danh mục đang được dùng bởi giao dịch.',
            ]);
        }

        $category->delete();
    }

    /**
     * @return array{users: int, transactions: int, categories: int}
     */
    public function adminStats(): array
    {
        return [
            'users' => User::query()->count(),
            'transactions' => DB::table('transactions')->count(),
            'categories' => Category::query()->count(),
        ];
    }
}

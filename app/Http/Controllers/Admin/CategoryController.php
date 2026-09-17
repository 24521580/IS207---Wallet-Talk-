<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        return view('admin.categories.index', [
            'categories' => Category::query()->withCount('transactions')->orderBy('type')->orderBy('name')->get(),
            'stats' => $this->categoryService->adminStats(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);
        $this->categoryService->create($request->validated());

        return back()->with('status', 'Đã thêm danh mục.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $this->categoryService->update($category, $request->validated());

        return back()->with('status', 'Đã cập nhật danh mục.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        $this->categoryService->delete($category);

        return back()->with('status', 'Đã xóa danh mục.');
    }
}

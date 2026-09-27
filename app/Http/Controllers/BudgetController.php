<?php

namespace App\Http\Controllers;

use App\Http\Requests\Budget\StoreBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgetService) {}

    /**
     * Trang quản lý hạn mức: danh sách budgets tháng đang xem + form tạo/sửa.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Budget::class);

        $user  = $request->user();
        $month = (int) $request->query('month', now()->month);
        $year  = (int) $request->query('year',  now()->year);

        // Clamp hợp lệ
        $month = max(1, min(12, $month));
        $year  = max(2000, min(2100, $year));

        $budgetRows    = $this->budgetService->forUserMonth($user, $month, $year);
        $existingCatIds = $budgetRows->pluck('budget.category_id')->all();

        // Chỉ hiện category expense trong dropdown "Thêm mới"
        $availableCategories = Category::query()
            ->where('type', Category::TYPE_EXPENSE)
            ->orderBy('name')
            ->get();

        return view('budgets.index', [
            'budgetRows'          => $budgetRows,
            'availableCategories' => $availableCategories,
            'existingCatIds'      => $existingCatIds,
            'month'               => $month,
            'year'                => $year,
        ]);
    }

    /**
     * Tạo mới hoặc cập nhật ngân sách (upsert).
     */
    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $this->authorize('create', Budget::class);

        $data = $request->validated();
        $this->budgetService->upsert(
            $request->user(),
            (int) $data['category_id'],
            (int) $data['month'],
            (int) $data['year'],
            (int) $data['limit_amount'],
        );

        return redirect()
            ->route('budgets.index', ['month' => $data['month'], 'year' => $data['year']])
            ->with('status', 'Đã lưu hạn mức chi tiêu.');
    }

    /**
     * Cập nhật limit_amount của một budget đã có.
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $this->budgetService->upsert(
            $request->user(),
            $budget->category_id,
            $budget->month,
            $budget->year,
            (int) $request->validated('limit_amount'),
        );

        return redirect()
            ->route('budgets.index', ['month' => $budget->month, 'year' => $budget->year])
            ->with('status', 'Đã cập nhật hạn mức.');
    }

    /**
     * Xóa budget.
     */
    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $month = $budget->month;
        $year  = $budget->year;

        $this->budgetService->delete($budget);

        return redirect()
            ->route('budgets.index', ['month' => $month, 'year' => $year])
            ->with('status', 'Đã xóa hạn mức.');
    }
}

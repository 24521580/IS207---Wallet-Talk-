<?php

namespace App\Http\Controllers;

use App\Exceptions\AiParseException;
use App\Http\Requests\Ai\ParseExpenseRequest;
use App\Http\Requests\Transaction\ConfirmTransactionsRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Ai\ExpenseParserService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly ExpenseParserService $expenseParserService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Transaction::class);

        $filters = $request->only(['q', 'from', 'to', 'category_id', 'type']);
        $transactions = $this->transactionService->paginateForUser($request->user(), $filters);

        return view('transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'categories' => Category::query()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Transaction::class);

        return view('transactions.create', [
            'categories' => Category::query()->orderBy('type')->orderBy('name')->get(),
            'demoMode' => (bool) config('ai.demo_mode'),
        ]);
    }

    public function parse(ParseExpenseRequest $request): JsonResponse
    {
        $this->authorize('create', Transaction::class);

        try {
            $result = $this->expenseParserService->parse($request->validated('text'));
        } catch (AiParseException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'Có lỗi xảy ra. Vui lòng thử lại.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => count($result['transactions']) > 0
                ? 'AI đã nhận diện '.count($result['transactions']).' giao dịch.'
                : 'AI không nhận diện được giao dịch nào. Hãy thêm thủ công.',
            'data' => $result,
        ]);
    }

    public function confirm(ConfirmTransactionsRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $saved = $this->transactionService->confirmMany(
            $request->user(),
            $request->validated('transactions')
        );

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Đã lưu '.$saved->count().' giao dịch.');
    }

    public function edit(Transaction $transaction): View
    {
        $this->authorize('update', $transaction);

        return view('transactions.edit', [
            'transaction' => $transaction,
            'categories' => Category::query()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $this->transactionService->update($transaction, $request->validated());

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Đã cập nhật giao dịch.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->transactionService->delete($transaction);

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Đã xóa giao dịch.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\AiParseException;
use App\Http\Requests\Ai\ParseExpenseRequest;
use App\Http\Requests\Transaction\ConfirmTransactionsRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Ai\ExpenseParserService;
use App\Services\Ai\LiveAiClient;
use App\Services\BudgetService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly ExpenseParserService $expenseParserService,
        private readonly BudgetService $budgetService,
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

        $demoMode = (bool) config('ai.demo_mode');
        $liveClient = app(LiveAiClient::class);
        $aiProvider = $liveClient->resolveProvider();

        return view('transactions.create', [
            'categories' => Category::query()->orderBy('type')->orderBy('name')->get(),
            'demoMode' => $demoMode,
            'aiProvider' => $aiProvider,
            'aiConfigured' => $demoMode || $liveClient->hasKeyFor($aiProvider),
        ]);
    }

    public function parse(ParseExpenseRequest $request): JsonResponse
    {
        $this->authorize('create', Transaction::class);

        $text = $request->validated('text');
        Log::info('TransactionController: Parse request received', [
            'text_length' => strlen($text),
            'text_preview' => substr($text, 0, 50),
            'user_id' => $request->user()->id,
        ]);

        try {
            $result = $this->expenseParserService->parse($text);

            Log::info('TransactionController: Parse successful', [
                'transaction_count' => count($result['transactions']),
                'demo' => $result['demo'] ?? false,
                'provider' => $result['provider'] ?? 'unknown',
            ]);
        } catch (AiParseException $exception) {
            Log::warning('Transaction AI parse failed.', [
                'error_type' => $exception->getErrorType(),
                'detail' => $exception->getDeveloperMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
                'error_type' => $exception->getErrorType(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Transaction AI parse error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Có lỗi xảy ra trong quá trình xử lý. Vui lòng thử lại.',
            ], 500);
        }

        // ── Budget check (backend là nguồn sự thật, không tin AI) ──────────────
        // Chỉ kiểm tra những transactions có category_id (đã được validator map)
        $budgetWarnings = [];
        try {
            $checkableItems = array_filter(
                $result['transactions'],
                fn (array $t) => isset($t['category_id']) && isset($t['amount']) && isset($t['transaction_date']),
            );
            if (! empty($checkableItems)) {
                $budgetWarnings = $this->budgetService->checkBudgets($request->user(), array_values($checkableItems));
            }
        } catch (\Throwable $e) {
            // Budget check failure không được phá vỡ AI parse — chỉ log và bỏ qua
            Log::warning('Budget check failed silently.', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'message' => count($result['transactions']) > 0
                ? 'AI đã nhận diện '.count($result['transactions']).' giao dịch.'
                : 'AI không nhận diện được giao dịch nào. Hãy thêm thủ công.',
            'data' => $result,
            'budget_warnings' => $budgetWarnings,
        ]);
    }

    public function confirm(ConfirmTransactionsRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        try {
            $saved = $this->transactionService->confirmMany(
                $request->user(),
                $request->validated('transactions')
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $e) {
            Log::error('Transaction confirm failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()
                ->withErrors(['transactions' => 'Không thể lưu giao dịch. Vui lòng thử lại.'])
                ->withInput();
        }

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

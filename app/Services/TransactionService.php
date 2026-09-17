<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    /**
     * Persist many reviewed transactions in one request.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Transaction>
     */
    public function confirmMany(User $user, array $items): Collection
    {
        $this->assertCategoryTypes($items);

        return DB::transaction(function () use ($user, $items) {
            $saved = collect();

            foreach ($items as $item) {
                $saved->push($user->transactions()->create([
                    'category_id' => $item['category_id'],
                    'type' => $item['type'],
                    'amount' => (int) $item['amount'],
                    'transaction_date' => $item['transaction_date'],
                    'note' => $item['note'],
                    'source' => $item['source'] ?? Transaction::SOURCE_MANUAL,
                ]));
            }

            return $saved;
        });
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $this->assertCategoryTypes([$data]);
        $transaction->update($data);

        return $transaction->refresh();
    }

    public function delete(Transaction $transaction): void
    {
        $transaction->delete();
    }

    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->with('category')
            ->ownedBy($user)
            ->search($filters['q'] ?? null);

        if (! empty($filters['from'])) {
            $query->whereDate('transaction_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('transaction_date', '<=', $filters['to']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Category type must match the transaction type to keep reports honest.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function assertCategoryTypes(array $items): void
    {
        $ids = collect($items)->pluck('category_id')->unique()->filter()->all();
        $categories = Category::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($items as $item) {
            $category = $categories->get($item['category_id'] ?? null);
            if (! $category || $category->type !== ($item['type'] ?? null)) {
                throw ValidationException::withMessages([
                    'category_id' => 'Danh mục không khớp với loại giao dịch.',
                ]);
            }
        }
    }
}

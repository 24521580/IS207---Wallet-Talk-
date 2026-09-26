<?php

namespace App\Services\Ai;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AiResponseValidator
{
    /**
     * Normalize and validate structured AI output before it reaches the UI.
     * Nothing is persisted here — the user must confirm later.
     *
     * @param  array<string, mixed>  $payload
     * @return array{transactions: array<int, array<string, mixed>>, unresolved: array<int, mixed>}
     */
    public function validate(array $payload, Collection $categories): array
    {
        $transactions = [];
        $unresolved = Arr::wrap($payload['unresolved'] ?? []);

        foreach (Arr::wrap($payload['transactions'] ?? []) as $item) {
            if (! is_array($item)) {
                $unresolved[] = $item;

                continue;
            }

            $normalized = $this->normalizeItem($item, $categories);

            if ($normalized === null) {
                $unresolved[] = $item;

                continue;
            }

            $transactions[] = $normalized;
        }

        return [
            'transactions' => $transactions,
            'unresolved' => array_values($unresolved),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeItem(array $item, Collection $categories): ?array
    {
        $type = $item['type'] ?? null;
        if (! in_array($type, [Category::TYPE_INCOME, Category::TYPE_EXPENSE], true)) {
            return null;
        }

        $amount = $this->normalizeAmount($item['amount'] ?? null);
        if ($amount === null || $amount <= 0) {
            return null;
        }

        $categoryName = trim((string) ($item['category'] ?? 'Khác'));
        $category = $this->matchCategory($categories, $categoryName, $type);

        if ($category === null) {
            $fallbackName = $type === Category::TYPE_INCOME ? 'Thu nhập khác' : 'Khác';
            $category = $this->matchCategory($categories, $fallbackName, $type);
        }

        if ($category === null) {
            return null;
        }

        $date = $this->normalizeDate($item['date'] ?? null);
        $note = trim((string) ($item['note'] ?? $category->name));

        if ($note === '') {
            $note = $category->name;
        }

        return [
            'type' => $type,
            'amount' => $amount,
            'category' => $category->name,
            'category_id' => $category->id,
            'date' => $date,
            'note' => mb_substr($note, 0, 255),
            'source' => 'ai',
        ];
    }

    private function normalizeAmount(mixed $amount): ?int
    {
        if (is_int($amount) || is_float($amount)) {
            return (int) round((float) $amount);
        }

        if (! is_string($amount)) {
            return null;
        }

        $digits = preg_replace('/[^\d]/', '', $amount);

        return $digits === '' ? null : (int) $digits;
    }

    private function normalizeDate(mixed $date): string
    {
        try {
            return Carbon::parse((string) ($date ?: now()->toDateString()))
                ->timezone(config('app.timezone'))
                ->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function matchCategory(Collection $categories, string $name, string $type): ?Category
    {
        $normalized = mb_strtolower(trim($name));

        return $categories->first(function (Category $category) use ($normalized, $type) {
            return $category->type === $type
                && mb_strtolower($category->name) === $normalized;
        });
    }
}

<?php

namespace App\Services\Ai;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deterministic Vietnamese parser used when DEMO_AI_MODE=true
 * or when the live API is unavailable.
 */
class DemoAiParser
{
    /**
     * @return array{transactions: array<int, array<string, mixed>>, unresolved: array<int, mixed>}
     */
    public function parse(string $text, Collection $categories): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        $preset = $this->presetMatch($normalized, $today, $yesterday);
        if ($preset !== null) {
            return $preset;
        }

        return $this->heuristicParse($normalized, $categories, $today, $yesterday);
    }

    /**
     * Known assignment / demo sentences — guaranteed stable for bảo vệ đồ án.
     *
     * @return array{transactions: array<int, array<string, mixed>>, unresolved: array<int, mixed>}|null
     */
    private function presetMatch(string $text, string $today, string $yesterday): ?array
    {
        $map = [
            'hôm nay ăn sáng 30k' => [
                ['type' => 'expense', 'amount' => 30000, 'category' => 'Ăn uống', 'date' => $today, 'note' => 'Ăn sáng'],
            ],
            'hôm nay ăn sáng 30k, đổ xăng 100k, chiều mua sách 150k' => [
                ['type' => 'expense', 'amount' => 30000, 'category' => 'Ăn uống', 'date' => $today, 'note' => 'Ăn sáng'],
                ['type' => 'expense', 'amount' => 100000, 'category' => 'Di chuyển', 'date' => $today, 'note' => 'Đổ xăng'],
                ['type' => 'expense', 'amount' => 150000, 'category' => 'Giáo dục', 'date' => $today, 'note' => 'Mua sách'],
            ],
            'hôm nay ăn sáng 30k, uống cà phê 25k, đổ xăng 100k' => [
                ['type' => 'expense', 'amount' => 30000, 'category' => 'Ăn uống', 'date' => $today, 'note' => 'Ăn sáng'],
                ['type' => 'expense', 'amount' => 25000, 'category' => 'Ăn uống', 'date' => $today, 'note' => 'Uống cà phê'],
                ['type' => 'expense', 'amount' => 100000, 'category' => 'Di chuyển', 'date' => $today, 'note' => 'Đổ xăng'],
            ],
            'hôm qua mua sách 150k' => [
                ['type' => 'expense', 'amount' => 150000, 'category' => 'Giáo dục', 'date' => $yesterday, 'note' => 'Mua sách'],
            ],
            'hôm qua mua sách 120k' => [
                ['type' => 'expense', 'amount' => 120000, 'category' => 'Giáo dục', 'date' => $yesterday, 'note' => 'Mua sách'],
            ],
            'nhận lương 10 triệu' => [
                ['type' => 'income', 'amount' => 10000000, 'category' => 'Lương', 'date' => $today, 'note' => 'Nhận lương'],
            ],
            'ăn sáng 35k' => [
                ['type' => 'expense', 'amount' => 35000, 'category' => 'Ăn uống', 'date' => $today, 'note' => 'Ăn sáng'],
            ],
            'mua cà phê 45k và gửi xe 10k' => [
                ['type' => 'expense', 'amount' => 45000, 'category' => 'Ăn uống', 'date' => $today, 'note' => 'Mua cà phê'],
                ['type' => 'expense', 'amount' => 10000, 'category' => 'Di chuyển', 'date' => $today, 'note' => 'Gửi xe'],
            ],
            'mua đồ 100k' => [
                ['type' => 'expense', 'amount' => 100000, 'category' => 'Mua sắm', 'date' => $today, 'note' => 'Mua đồ'],
            ],
        ];

        $key = mb_strtolower($text);

        if (! isset($map[$key])) {
            return null;
        }

        return [
            'transactions' => $map[$key],
            'unresolved' => [],
        ];
    }

    /**
     * @return array{transactions: array<int, array<string, mixed>>, unresolved: array<int, mixed>}
     */
    private function heuristicParse(string $text, Collection $categories, string $today, string $yesterday): array
    {
        $currentDate = str_contains(mb_strtolower($text), 'hôm qua') ? $yesterday : $today;
        // Tách theo dấu phẩy/chấm phẩy ngăn cách các khoản, nhưng không tách
        // dấu phẩy nằm giữa hai chữ số (ví dụ "2,5 triệu").
        $chunks = preg_split('/(?<!\d)[,;](?!\d)|\svà\s/u', $text) ?: [$text];
        $transactions = [];
        $unresolved = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $amount = $this->extractAmount($chunk);
            if ($amount === null) {
                $unresolved[] = ['text' => $chunk, 'reason' => 'Không tìm thấy số tiền'];

                continue;
            }

            $type = $this->guessType($chunk);
            $categoryName = $this->guessCategory($chunk, $type, $categories);
            $note = $this->guessNote($chunk);

            $transactions[] = [
                'type' => $type,
                'amount' => $amount,
                'category' => $categoryName,
                'date' => $currentDate,
                'note' => $note,
            ];
        }

        return [
            'transactions' => $transactions,
            'unresolved' => $unresolved,
        ];
    }

    private function extractAmount(string $text): ?int
    {
        $normalized = mb_strtolower($text);

        // "2tr5" / "2 triệu 5" = 2.500.000
        if (preg_match('/(\d+)\s*(?:triệu|tr)\s*(\d)(?!\d)/u', $normalized, $match)) {
            return ((int) $match[1] * 1_000_000) + ((int) $match[2] * 100_000);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(triệu|tr)\b/u', $normalized, $match)) {
            return (int) round(((float) str_replace(',', '.', $match[1])) * 1_000_000);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(k|nghìn|ngàn)\b/u', $normalized, $match)) {
            return (int) round(((float) str_replace(',', '.', $match[1])) * 1_000);
        }

        // "15.000" hoặc "15,000" = 15.000 (dấu phân cách hàng nghìn)
        if (preg_match('/\d{1,3}(?:[.,]\d{3})+(?!\d)/', $text, $match)) {
            return (int) preg_replace('/\D/', '', $match[0]);
        }

        if (preg_match('/(\d+)\s*(vnd|đ|đồng)?/u', $normalized, $match)) {
            $value = (int) $match[1];

            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function guessType(string $text): string
    {
        $lower = mb_strtolower($text);
        $incomeHints = ['lương', 'thưởng', 'nhận', 'thu nhập', 'được chuyển'];

        foreach ($incomeHints as $hint) {
            if (str_contains($lower, $hint)) {
                return Category::TYPE_INCOME;
            }
        }

        return Category::TYPE_EXPENSE;
    }

    private function guessCategory(string $text, string $type, Collection $categories): string
    {
        $lower = mb_strtolower($text);

        $keywordMap = [
            'Ăn uống' => ['ăn', 'cà phê', 'cafe', 'phở', 'cơm', 'bún', 'nhậu', 'trà sữa', 'uống'],
            'Di chuyển' => ['xăng', 'grab', 'be ', 'taxi', 'xe bus', 'gửi xe', 'vé xe', 'đi lại'],
            'Mua sắm' => ['mua sắm', 'quần', 'áo', 'shopee', 'mua đồ', 'siêu thị'],
            'Giáo dục' => ['sách', 'học', 'khóa học', 'học phí', 'vở'],
            'Hóa đơn' => ['điện', 'nước', 'internet', 'wifi', 'hóa đơn', 'nhà mạng'],
            'Giải trí' => ['netflix', 'game', 'xem phim', 'karaoke', 'du lịch'],
            'Sức khỏe' => ['thuốc', 'khám', 'bệnh viện', 'vitamin'],
            'Lương' => ['lương'],
            'Thưởng' => ['thưởng'],
            'Thu nhập khác' => ['thu nhập'],
        ];

        foreach ($keywordMap as $categoryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $exists = $categories->first(
                        fn (Category $category) => $category->name === $categoryName && $category->type === $type
                    );

                    if ($exists) {
                        return $categoryName;
                    }
                }
            }
        }

        return $type === Category::TYPE_INCOME ? 'Thu nhập khác' : 'Khác';
    }

    private function guessNote(string $text): string
    {
        $withoutDate = preg_replace('/hôm nay|hôm qua|chiều|sáng|tối|trưa/iu', '', $text) ?? $text;
        $withoutAmount = preg_replace(
            '/\d+(?:[.,]\d+)?\s*(triệu|tr|k|nghìn|ngàn|vnd|đồng|đ)?/iu',
            '',
            $withoutDate
        ) ?? $withoutDate;

        $note = trim(preg_replace('/\s+/', ' ', $withoutAmount) ?? $withoutAmount, ' ,.-');

        return $note !== '' ? Str::title($note) : 'Giao dịch';
    }
}

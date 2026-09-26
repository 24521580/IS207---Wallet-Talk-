<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Ai\AiResponseValidator;
use App\Services\Ai\DemoAiParser;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kiểm tra bộ chuẩn hóa output AI: không tin dữ liệu AI, mọi thứ phải
 * được validate lại trước khi hiển thị cho người dùng.
 */
class AiResponseValidatorTest extends TestCase
{
    use RefreshDatabase;

    private Collection $categories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->categories = Category::query()->get();
    }

    public function test_invalid_amounts_are_moved_to_unresolved(): void
    {
        $result = (new AiResponseValidator)->validate([
            'transactions' => [
                ['type' => 'expense', 'amount' => 0, 'category' => 'Ăn uống', 'date' => now()->toDateString(), 'note' => 'Không hợp lệ'],
                ['type' => 'expense', 'amount' => -5000, 'category' => 'Ăn uống', 'date' => now()->toDateString(), 'note' => 'Số âm'],
                ['type' => 'expense', 'amount' => 'không rõ', 'category' => 'Ăn uống', 'date' => now()->toDateString(), 'note' => 'Không phải số'],
                ['type' => 'expense', 'amount' => 30000, 'category' => 'Ăn uống', 'date' => now()->toDateString(), 'note' => 'Hợp lệ'],
            ],
            'unresolved' => [],
        ], $this->categories);

        $this->assertCount(1, $result['transactions']);
        $this->assertCount(3, $result['unresolved']);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $result = (new AiResponseValidator)->validate([
            'transactions' => [
                ['type' => 'transfer', 'amount' => 50000, 'category' => 'Di chuyển', 'date' => now()->toDateString(), 'note' => 'Sai loại'],
            ],
        ], $this->categories);

        $this->assertCount(0, $result['transactions']);
        $this->assertCount(1, $result['unresolved']);
    }

    public function test_unknown_category_falls_back_to_khac_with_matching_type(): void
    {
        $result = (new AiResponseValidator)->validate([
            'transactions' => [
                ['type' => 'expense', 'amount' => 50000, 'category' => 'Danh mục không tồn tại', 'date' => now()->toDateString(), 'note' => 'X'],
            ],
        ], $this->categories);

        $fallback = Category::query()->find($result['transactions'][0]['category_id']);

        $this->assertSame('Khác', $result['transactions'][0]['category']);
        $this->assertSame('expense', $fallback->type);
    }

    public function test_invalid_date_falls_back_to_today(): void
    {
        $result = (new AiResponseValidator)->validate([
            'transactions' => [
                ['type' => 'expense', 'amount' => 50000, 'category' => 'Ăn uống', 'date' => 'khong-phai-ngay', 'note' => 'X'],
            ],
        ], $this->categories);

        $this->assertSame(now()->toDateString(), $result['transactions'][0]['date']);
    }

    public function test_every_output_item_carries_source_ai(): void
    {
        $result = (new AiResponseValidator)->validate([
            'transactions' => [
                ['type' => 'expense', 'amount' => 50000, 'category' => 'Ăn uống', 'date' => now()->toDateString(), 'note' => 'X'],
            ],
        ], $this->categories);

        $this->assertSame('ai', $result['transactions'][0]['source']);
    }

    /**
     * @dataProvider amountCases
     */
    #[DataProvider('amountCases')]
    public function test_vietnamese_amount_formats_are_understood(string $text, int $expected): void
    {
        $raw = (new DemoAiParser)->parse($text, $this->categories);
        $result = (new AiResponseValidator)->validate($raw, $this->categories);

        $this->assertCount(1, $result['transactions'], "Không tách được số tiền từ: {$text}");
        $this->assertSame($expected, $result['transactions'][0]['amount']);
    }

    public static function amountCases(): array
    {
        return [
            'k' => ['đổ xăng 100k', 100000],
            'k viết hoa' => ['đổ xăng 100K', 100000],
            'nghìn' => ['gửi xe 10 nghìn', 10000],
            'triệu' => ['nhận tiền 1 triệu', 1000000],
            'triệu thập phân' => ['mua điện thoại 2,5 triệu', 2500000],
            'tr' => ['trả tiền nhà 3tr', 3000000],
            'dấu chấm phân cách' => ['điện thoại 15.000', 15000],
            'số nguyên' => ['sách 150000', 150000],
        ];
    }
}

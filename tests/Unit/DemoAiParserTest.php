<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Ai\AiResponseValidator;
use App\Services\Ai\DemoAiParser;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAiParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_case_1_single_breakfast(): void
    {
        $result = $this->parse('Hôm nay ăn sáng 30k');
        $this->assertCount(1, $result['transactions']);
        $this->assertSame(30000, $result['transactions'][0]['amount']);
        $this->assertSame('expense', $result['transactions'][0]['type']);
        $this->assertSame(now()->toDateString(), $result['transactions'][0]['date']);
    }

    public function test_case_2_three_transactions(): void
    {
        $result = $this->parse('Hôm nay ăn sáng 30k, uống cà phê 25k, đổ xăng 100k');
        $this->assertCount(3, $result['transactions']);
    }

    public function test_case_3_yesterday(): void
    {
        $result = $this->parse('Hôm qua mua sách 150k');
        $this->assertSame(now()->subDay()->toDateString(), $result['transactions'][0]['date']);
        $this->assertSame(150000, $result['transactions'][0]['amount']);
    }

    public function test_case_4_salary(): void
    {
        $result = $this->parse('Nhận lương 10 triệu');
        $this->assertSame('income', $result['transactions'][0]['type']);
        $this->assertSame(10_000_000, $result['transactions'][0]['amount']);
    }

    public function test_case_5_ambiguous_shopping(): void
    {
        $result = $this->parse('mua đồ 100k');
        $this->assertCount(1, $result['transactions']);
        $this->assertContains($result['transactions'][0]['category'], ['Mua sắm', 'Khác']);
    }

    /**
     * @return array{transactions: array<int, array<string, mixed>>, unresolved: array<int, mixed>}
     */
    private function parse(string $text): array
    {
        $categories = Category::query()->get();
        $raw = (new DemoAiParser)->parse($text, $categories);

        return (new AiResponseValidator)->validate($raw, $categories);
    }
}

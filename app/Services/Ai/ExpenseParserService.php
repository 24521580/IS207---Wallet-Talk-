<?php

namespace App\Services\Ai;

use App\Exceptions\AiParseException;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Log;

class ExpenseParserService
{
    public function __construct(
        private readonly DemoAiParser $demoParser,
        private readonly LiveAiClient $liveClient,
        private readonly AiResponseValidator $validator,
    ) {}

    /**
     * Parse natural language into structured transactions.
     * Results are never saved here — they are only prepared for human review.
     *
     * @return array{
     *     transactions: array<int, array<string, mixed>>,
     *     unresolved: array<int, mixed>,
     *     demo: bool,
     *     provider: string
     * }
     */
    public function parse(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            throw AiParseException::emptyInput();
        }

        // Ensure database categories are available even if seeders weren't run on Railway
        $categories = Category::query()->orderBy('name')->get();
        if ($categories->isEmpty()) {
            try {
                (new CategorySeeder())->run();
                $categories = Category::query()->orderBy('name')->get();
            } catch (\Throwable $e) {
                Log::warning('Auto-seed categories failed: '.$e->getMessage());
            }
        }

        $demoMode = (bool) config('ai.demo_mode');

        if ($demoMode) {
            // DEMO_AI_MODE=true -> deterministic demo parser
            $raw = $this->demoParser->parse($text, $categories);
            $validated = $this->validator->validate(is_array($raw) ? $raw : [], $categories);

            return [
                ...$validated,
                'demo' => true,
                'provider' => 'demo',
            ];
        }

        // DEMO_AI_MODE=false -> live AI call. Do NOT mask errors with demo mode.
        $provider = $this->liveClient->resolveProvider();
        $raw = $this->liveClient->parse(
            $text,
            $categories->map(fn (Category $category) => [
                'name' => $category->name,
                'type' => $category->type,
            ])->all(),
            now()->toDateString(),
        );

        $validated = $this->validator->validate(is_array($raw) ? $raw : [], $categories);

        return [
            ...$validated,
            'demo' => false,
            'provider' => $provider,
        ];
    }
}

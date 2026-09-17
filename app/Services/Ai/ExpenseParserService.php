<?php

namespace App\Services\Ai;

use App\Exceptions\AiParseException;
use App\Models\Category;
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

        $categories = Category::query()->orderBy('name')->get();
        $demoForced = (bool) config('ai.demo_mode');
        $usedDemo = $demoForced;
        $raw = null;

        if (! $demoForced) {
            try {
                $raw = $this->liveClient->parse(
                    $text,
                    $categories->map(fn (Category $category) => [
                        'name' => $category->name,
                        'type' => $category->type,
                    ])->all(),
                    now()->toDateString(),
                );
            } catch (AiParseException $exception) {
                Log::warning('Live AI parse failed, falling back to demo parser.', [
                    'message' => $exception->getMessage(),
                ]);
                $usedDemo = true;
            }
        }

        if ($usedDemo) {
            $raw = $this->demoParser->parse($text, $categories);
        }

        $validated = $this->validator->validate(is_array($raw) ? $raw : [], $categories);

        return [
            ...$validated,
            'demo' => $usedDemo,
            'provider' => $usedDemo ? 'demo' : (string) config('ai.provider'),
        ];
    }
}

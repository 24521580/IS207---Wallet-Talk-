<?php

namespace App\Services\Ai;

use App\Exceptions\AiParseException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LiveAiClient
{
    /**
     * @param  array<int, array{name: string, type: string}>  $categoryCatalog
     */
    public function parse(string $text, array $categoryCatalog, string $today): array
    {
        $provider = config('ai.provider');

        return match ($provider) {
            'openai' => $this->callOpenAi($text, $categoryCatalog, $today),
            'groq' => $this->callGroq($text, $categoryCatalog, $today),
            default => $this->callGemini($text, $categoryCatalog, $today),
        };
    }

    /**
     * @param  array<int, array{name: string, type: string}>  $categoryCatalog
     */
    private function callGemini(string $text, array $categoryCatalog, string $today): array
    {
        $apiKey = config('ai.gemini.api_key');
        if (! filled($apiKey)) {
            throw AiParseException::missingKey();
        }

        $model = config('ai.gemini.model');
        $endpoint = rtrim((string) config('ai.gemini.endpoint'), '/').'/'.$model.':generateContent';

        try {
            $response = Http::timeout((int) config('ai.timeout'))
                ->acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => (string) $apiKey,
                ])
                ->post($endpoint, [
                    'contents' => [[
                        'parts' => [['text' => $this->userPrompt($text, $categoryCatalog, $today)]],
                    ]],
                    'systemInstruction' => [
                        'parts' => [['text' => $this->systemPrompt()]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (ConnectionException) {
            throw AiParseException::timeout();
        } catch (\Throwable) {
            throw AiParseException::unavailable();
        }

        if (! $response->successful()) {
            throw AiParseException::unavailable();
        }

        $textPayload = data_get($response->json(), 'candidates.0.content.parts.0.text');

        return $this->decodeJson((string) $textPayload);
    }

    /**
     * @param  array<int, array{name: string, type: string}>  $categoryCatalog
     */
    private function callOpenAi(string $text, array $categoryCatalog, string $today): array
    {
        $apiKey = config('ai.openai.api_key');
        if (! filled($apiKey)) {
            throw AiParseException::missingKey();
        }

        try {
            $response = Http::timeout((int) config('ai.timeout'))
                ->withToken((string) $apiKey)
                ->acceptJson()
                ->post((string) config('ai.openai.endpoint'), [
                    'model' => config('ai.openai.model'),
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($text, $categoryCatalog, $today)],
                    ],
                ]);
        } catch (ConnectionException) {
            throw AiParseException::timeout();
        } catch (\Throwable) {
            throw AiParseException::unavailable();
        }

        if (! $response->successful()) {
            throw AiParseException::unavailable();
        }

        return $this->decodeJson((string) data_get($response->json(), 'choices.0.message.content'));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Bạn là bộ máy phân tích chi tiêu, không phải chatbot.
Nhiệm vụ: tách câu tiếng Việt thành JSON giao dịch.
Quy tắc:
- Chỉ tạo giao dịch từ số tiền thực sự xuất hiện trong câu. Không bịa khoản mới.
- "30k" = 30000, "100k" = 100000, "1 triệu" = 1000000 (đơn vị VND, số nguyên).
- "hôm nay" = ngày hiện tại được cung cấp. "hôm qua" = ngày hôm qua.
- type chỉ được "income" hoặc "expense".
- Nếu không chắc category, dùng "Khác" (chi) hoặc "Thu nhập khác" (thu), hoặc đưa vào unresolved.
- Trả về đúng JSON: {"transactions":[{"type":"expense","amount":30000,"category":"Ăn uống","date":"YYYY-MM-DD","note":"..."}],"unresolved":[]}
PROMPT;
    }

    /**
     * @param  array<int, array{name: string, type: string}>  $categoryCatalog
     */
    private function userPrompt(string $text, array $categoryCatalog, string $today): string
    {
        $yesterday = Carbon::parse($today)->subDay()->toDateString();
        $catalog = collect($categoryCatalog)
            ->map(fn (array $row) => $row['name'].' ('.$row['type'].')')
            ->implode(', ');

        return "Hôm nay = {$today}. Hôm qua = {$yesterday}.\nDanh mục hợp lệ: {$catalog}\nCâu cần phân tích: {$text}";
    }

    private function decodeJson(string $raw): array
    {
        $clean = trim($raw);
        $clean = Str::of($clean)->replace(['```json', '```'], '')->trim()->toString();

        $decoded = json_decode($clean, true);
        if (! is_array($decoded)) {
            throw AiParseException::invalidJson();
        }

        return $decoded;
    }
}

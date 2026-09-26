<?php

namespace App\Services\Ai;

use App\Exceptions\AiParseException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiveAiClient
{
    /**
     * Resolve the active AI provider, with intelligent auto-detection based on configured keys.
     */
    public function resolveProvider(): string
    {
        $explicit = strtolower((string) config('ai.provider', ''));
        if (in_array($explicit, ['gemini', 'openai', 'groq'], true) && $this->hasKeyFor($explicit)) {
            return $explicit;
        }

        if ($this->hasKeyFor('gemini')) {
            return 'gemini';
        }
        if ($this->hasKeyFor('openai')) {
            return 'openai';
        }
        if ($this->hasKeyFor('groq')) {
            return 'groq';
        }

        return $explicit !== '' ? $explicit : 'gemini';
    }

    public function hasKeyFor(string $provider): bool
    {
        return filled($this->apiKey($provider));
    }

    public function getModel(string $provider): string
    {
        return match ($provider) {
            'openai' => (string) config('ai.openai.model', 'gpt-4o-mini'),
            'groq' => (string) config('ai.groq.model', 'llama-3.3-70b-versatile'),
            default => (string) config('ai.gemini.model', 'gemini-2.0-flash'),
        };
    }

    /**
     * @param  array<int, array{name: string, type: string}>  $categoryCatalog
     */
    public function parse(string $text, array $categoryCatalog, string $today): array
    {
        $provider = $this->resolveProvider();

        return match ($provider) {
            'openai' => $this->callOpenAi($text, $categoryCatalog, $today),
            'groq' => $this->callGroq($text, $categoryCatalog, $today),
            default => $this->callGemini($text, $categoryCatalog, $today),
        };
    }

    /**
     * Lightweight connectivity check (lists models). Does not persist data or expose secrets.
     *
     * @return array{
     *     configured: bool,
     *     provider: string,
     *     model: string,
     *     connection: string,
     *     http_status?: int,
     *     error_type?: string,
     *     message?: string
     * }
     */
    public function testConnection(?string $provider = null): array
    {
        $provider = $provider ?: $this->resolveProvider();
        $model = $this->getModel($provider);
        $configured = $this->hasKeyFor($provider);

        if (! $configured) {
            return [
                'configured' => false,
                'provider' => $provider,
                'model' => $model,
                'connection' => 'failed',
                'error_type' => 'missing_key',
                'message' => 'Chưa cấu hình API key trong biến môi trường.',
            ];
        }

        try {
            $ping = $this->pingProvider($provider);

            if ($ping['ok']) {
                return [
                    'configured' => true,
                    'provider' => $provider,
                    'model' => $model,
                    'connection' => 'ok',
                    'http_status' => $ping['status'],
                    'message' => 'Kết nối thành công tới API '.$provider,
                ];
            }

            return [
                'configured' => true,
                'provider' => $provider,
                'model' => $model,
                'connection' => 'failed',
                'http_status' => $ping['status'],
                'error_type' => $ping['error_type'],
                'message' => $ping['message'],
            ];
        } catch (ConnectionException $e) {
            Log::error('LiveAiClient: AI ping timed out.', [
                'provider' => $provider,
                'error' => $this->redact($e->getMessage()),
            ]);

            return [
                'configured' => true,
                'provider' => $provider,
                'model' => $model,
                'connection' => 'failed',
                'error_type' => 'timeout',
                'message' => 'AI phản hồi quá lâu. Vui lòng thử lại hoặc nhập thủ công.',
            ];
        } catch (\Throwable $e) {
            Log::error('LiveAiClient: AI ping failed.', [
                'provider' => $provider,
                'error' => $this->redact($e->getMessage()),
            ]);

            return [
                'configured' => true,
                'provider' => $provider,
                'model' => $model,
                'connection' => 'failed',
                'error_type' => 'network',
                'message' => 'Lỗi kết nối mạng khi gọi AI. Vui lòng kiểm tra lại đường truyền.',
            ];
        }
    }

    /**
     * @param  array<int, array{name: string, type: string}>  $categoryCatalog
     */
    private function callGemini(string $text, array $categoryCatalog, string $today): array
    {
        $apiKey = $this->apiKey('gemini');
        if (! filled($apiKey)) {
            Log::warning('LiveAiClient: Gemini API key is missing.');
            throw AiParseException::missingKey('gemini');
        }

        $endpointBase = rtrim((string) config('ai.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
        $timeout = $this->timeout();
        $models = $this->geminiModels();
        $lastException = null;

        foreach ($models as $model) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout(10)
                    ->acceptJson()
                    ->withHeaders([
                        'x-goog-api-key' => (string) $apiKey,
                    ])
                    ->post($endpointBase.'/'.$model.':generateContent', [
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
            } catch (ConnectionException $e) {
                Log::error('LiveAiClient: Gemini request timed out.', ['error' => $this->redact($e->getMessage())]);
                throw AiParseException::timeout('gemini');
            } catch (\Throwable $e) {
                Log::error('LiveAiClient: Gemini network error.', ['error' => $this->redact($e->getMessage())]);
                throw AiParseException::networkError('gemini', $this->redact($e->getMessage()));
            }

            $isLastModel = $model === $models[array_key_last($models)];
            if ($response->status() === 404 && ! $isLastModel) {
                Log::warning('LiveAiClient: Gemini model not found, trying fallback.', ['model' => $model]);
                continue;
            }

            $this->throwIfUnsuccessful($response, 'gemini');

            $textPayload = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! filled($textPayload)) {
                Log::error('LiveAiClient: Gemini returned empty content part.');
                $lastException = AiParseException::invalidJson('gemini', 'Empty text payload');
                continue;
            }

            return $this->decodeJson((string) $textPayload);
        }

        throw $lastException ?? AiParseException::unavailable('gemini', 'All Gemini models failed');
    }

    /**
     * @param  array<int, array{name: string, type: string}>  $categoryCatalog
     */
    private function callOpenAi(string $text, array $categoryCatalog, string $today): array
    {
        $apiKey = $this->apiKey('openai');
        if (! filled($apiKey)) {
            Log::warning('LiveAiClient: OpenAI API key is missing.');
            throw AiParseException::missingKey('openai');
        }

        $model = $this->getModel('openai');
        $endpoint = (string) config('ai.openai.endpoint', 'https://api.openai.com/v1/chat/completions');

        try {
            $response = Http::timeout($this->timeout())
                ->connectTimeout(10)
                ->withToken((string) $apiKey)
                ->acceptJson()
                ->post($endpoint, [
                    'model' => $model,
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($text, $categoryCatalog, $today)],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::error('LiveAiClient: OpenAI request timed out.', ['error' => $this->redact($e->getMessage())]);
            throw AiParseException::timeout('openai');
        } catch (\Throwable $e) {
            Log::error('LiveAiClient: OpenAI network error.', ['error' => $this->redact($e->getMessage())]);
            throw AiParseException::networkError('openai', $this->redact($e->getMessage()));
        }

        $this->throwIfUnsuccessful($response, 'openai');

        $content = (string) data_get($response->json(), 'choices.0.message.content');

        return $this->decodeJson($content);
    }

    /**
     * @param  array<int, array{name: string, type: string}>  $categoryCatalog
     */
    private function callGroq(string $text, array $categoryCatalog, string $today): array
    {
        $apiKey = $this->apiKey('groq');
        if (! filled($apiKey)) {
            Log::warning('LiveAiClient: Groq API key is missing.');
            throw AiParseException::missingKey('groq');
        }

        $model = $this->getModel('groq');
        $endpoint = (string) config('ai.groq.endpoint', 'https://api.groq.com/openai/v1/chat/completions');

        try {
            $response = Http::timeout($this->timeout())
                ->connectTimeout(10)
                ->withToken((string) $apiKey)
                ->acceptJson()
                ->post($endpoint, [
                    'model' => $model,
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($text, $categoryCatalog, $today)],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::error('LiveAiClient: Groq request timed out.', ['error' => $this->redact($e->getMessage())]);
            throw AiParseException::timeout('groq');
        } catch (\Throwable $e) {
            Log::error('LiveAiClient: Groq network error.', ['error' => $this->redact($e->getMessage())]);
            throw AiParseException::networkError('groq', $this->redact($e->getMessage()));
        }

        $this->throwIfUnsuccessful($response, 'groq');

        $content = (string) data_get($response->json(), 'choices.0.message.content');

        return $this->decodeJson($content);
    }

    /**
     * @return array{ok: bool, status: int, error_type: string, message: string}
     */
    private function pingProvider(string $provider): array
    {
        $apiKey = $this->apiKey($provider);
        $timeout = min($this->timeout(), 15);

        $response = match ($provider) {
            'openai' => Http::timeout($timeout)
                ->connectTimeout(8)
                ->withToken((string) $apiKey)
                ->acceptJson()
                ->get('https://api.openai.com/v1/models'),
            'groq' => Http::timeout($timeout)
                ->connectTimeout(8)
                ->withToken((string) $apiKey)
                ->acceptJson()
                ->get('https://api.groq.com/openai/v1/models'),
            default => Http::timeout($timeout)
                ->connectTimeout(8)
                ->withHeaders(['x-goog-api-key' => (string) $apiKey])
                ->acceptJson()
                ->get('https://generativelanguage.googleapis.com/v1beta/models'),
        };

        $status = $response->status();

        if ($response->successful()) {
            return [
                'ok' => true,
                'status' => $status,
                'error_type' => '',
                'message' => 'ok',
            ];
        }

        $errorType = match (true) {
            in_array($status, [401, 403], true) => 'authentication',
            $status === 429 => 'rate_limit',
            $status === 404 => 'unavailable',
            default => 'unavailable',
        };

        $detail = $this->redact((string) data_get($response->json(), 'error.message', 'HTTP '.$status));
        Log::error('LiveAiClient: AI ping received error status.', [
            'provider' => $provider,
            'status' => $status,
            'error_detail' => $detail,
        ]);

        $userMessage = match ($errorType) {
            'authentication' => 'Khóa API không hợp lệ hoặc chưa được kích hoạt quyền truy cập.',
            'rate_limit' => 'Đã đạt giới hạn lượt gọi AI trong thời gian ngắn. Vui lòng đợi 1 phút và thử lại.',
            default => 'Không thể kết nối với AI. Vui lòng thử lại hoặc nhập thủ công.',
        };

        return [
            'ok' => false,
            'status' => $status,
            'error_type' => $errorType,
            'message' => $userMessage,
        ];
    }

    private function throwIfUnsuccessful(Response $response, string $provider): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $errDetail = $this->redact((string) data_get($response->json(), 'error.message', 'HTTP '.$status));
        Log::error('LiveAiClient: '.$provider.' API responded with error status.', [
            'status' => $status,
            'error_detail' => $errDetail,
        ]);

        if (in_array($status, [401, 403], true)) {
            throw AiParseException::authFailed($provider, $errDetail);
        }
        if ($status === 429) {
            throw AiParseException::rateLimited($provider);
        }

        throw AiParseException::unavailable($provider, $errDetail);
    }

    private function apiKey(string $provider): ?string
    {
        $key = match ($provider) {
            'openai' => config('ai.openai.api_key'),
            'groq' => config('ai.groq.api_key'),
            default => config('ai.gemini.api_key'),
        };

        if (! is_string($key) && $key !== null) {
            $key = (string) $key;
        }

        $key = is_string($key) ? trim($key, " \t\n\r\0\x0B\"'") : '';

        return $key === '' ? null : $key;
    }

    private function timeout(): int
    {
        return (int) config('ai.timeout', 25);
    }

    /**
     * @return array<int, string>
     */
    private function geminiModels(): array
    {
        $primary = $this->getModel('gemini');
        $fallbacks = config('ai.gemini.fallback_models', []);
        $list = array_merge([$primary], is_array($fallbacks) ? $fallbacks : []);

        return array_values(array_unique(array_filter($list, fn ($model) => is_string($model) && $model !== '')));
    }

    private function redact(string $value): string
    {
        $redacted = preg_replace('/(sk-|AIza|gsk_)[A-Za-z0-9_\-]+/', '[redacted]', $value) ?? $value;
        $redacted = preg_replace('/key=[^&\s]+/i', 'key=[redacted]', $redacted) ?? $redacted;

        return $redacted;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Bạn là bộ máy phân tích chi tiêu chuyên nghiệp cho ứng dụng "Ví Nói".
Nhiệm vụ: phân tích câu tiếng Việt thành cấu trúc JSON danh sách giao dịch.

Quy tắc bắt buộc:
1. Chỉ tạo giao dịch từ số tiền thực sự xuất hiện trong câu. Tuyệt đối không tự suy diễn hoặc bịa ra khoản mới.
2. Quy đổi đơn vị tiền tệ sang số nguyên VND:
   - "30k", "30 nghìn", "30 ngàn" = 30000
   - "100k", "100 ngàn" = 100000
   - "150k" = 150000
   - "1 triệu", "1tr" = 1000000
   - "2,5 triệu", "2.5 triệu", "2tr5" = 2500000
3. Xử lý ngày:
   - "hôm nay" = ngày hiện tại được cung cấp.
   - "hôm qua" = ngày hôm qua được cung cấp.
   - Mặc định là ngày hiện tại nếu không đề cập. Định dạng YYYY-MM-DD.
4. Loại giao dịch (type): chỉ được là "income" (thu) hoặc "expense" (chi).
5. Phân loại danh mục (category):
   - Phải khớp chính xác một trong các tên danh mục hợp lệ được cung cấp.
   - Nếu không chắc chắn, chọn "Khác" (cho chi) hoặc "Thu nhập khác" (cho thu), hoặc đưa đoạn văn vào "unresolved".
6. Cấu trúc JSON trả về:
{
  "transactions": [
    {
      "type": "expense",
      "amount": 30000,
      "category": "Ăn uống",
      "date": "YYYY-MM-DD",
      "note": "Ăn sáng"
    }
  ],
  "unresolved": []
}

Ví dụ:
Input: "Hôm nay ăn sáng 30k, đổ xăng 100k, chiều mua sách 150k" (Hôm nay: 2026-09-26)
Output:
{
  "transactions": [
    {"type": "expense", "amount": 30000, "category": "Ăn uống", "date": "2026-09-26", "note": "Ăn sáng"},
    {"type": "expense", "amount": 100000, "category": "Di chuyển", "date": "2026-09-26", "note": "Đổ xăng"},
    {"type": "expense", "amount": 150000, "category": "Giáo dục", "date": "2026-09-26", "note": "Mua sách"}
  ],
  "unresolved": []
}
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

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $clean, $matches)) {
            $clean = trim($matches[1]);
        }

        if (! Str::startsWith($clean, '{') && preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $clean = trim($matches[0]);
        }

        $decoded = json_decode($clean, true);
        if (! is_array($decoded)) {
            Log::error('LiveAiClient: Failed to decode JSON from AI payload.');
            throw AiParseException::invalidJson('ai', 'Invalid JSON syntax');
        }

        return $decoded;
    }
}

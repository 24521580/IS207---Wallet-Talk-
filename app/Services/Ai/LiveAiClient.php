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
            'groq' => (string) config('ai.groq.model', 'openai/gpt-oss-20b'),
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
                $isRateLimit = ($ping['error_type'] === 'rate_limit');

                return [
                    'configured' => true,
                    'provider' => $provider,
                    'model' => $model,
                    'connection' => $isRateLimit ? 'degraded' : 'ok',
                    'http_status' => $ping['status'],
                    'error_type' => $isRateLimit ? 'rate_limit' : null,
                    'message' => $isRateLimit ? $ping['message'] : 'Kết nối thành công tới API '.$provider,
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
     * Quick parse test with a minimal Vietnamese sentence. Safe for admin debug endpoints.
     * Returns the raw result or error details without persisting anything.
     *
     * @return array{ok: bool, provider: string, model: string, result?: array<mixed>, error_type?: string, message?: string}
     */
    public function testParse(?string $provider = null): array
    {
        $provider = $provider ?: $this->resolveProvider();
        $model = $this->getModel($provider);

        if (! $this->hasKeyFor($provider)) {
            return [
                'ok' => false,
                'provider' => $provider,
                'model' => $model,
                'error_type' => 'missing_key',
                'message' => 'Chưa cấu hình API key.',
            ];
        }

        try {
            $result = $this->parse(
                'Ăn sáng 30k',
                [['name' => 'Ăn uống', 'type' => 'expense'], ['name' => 'Khác', 'type' => 'expense']],
                now()->toDateString(),
            );

            return [
                'ok' => true,
                'provider' => $provider,
                'model' => $model,
                'result' => $result,
            ];
        } catch (\App\Exceptions\AiParseException $e) {
            return [
                'ok' => false,
                'provider' => $provider,
                'model' => $model,
                'error_type' => $e->getErrorType(),
                'message' => $e->getMessage(),
                'developer_message' => $e->getDeveloperMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'provider' => $provider,
                'model' => $model,
                'error_type' => 'unknown',
                'message' => $this->redact($e->getMessage()),
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
                Log::info('LiveAiClient: Calling Gemini API', [
                    'model' => $model,
                    'endpoint' => $endpointBase.'/'.$model.':generateContent',
                    'text_length' => strlen($text),
                    'category_count' => count($categoryCatalog),
                ]);

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

                Log::info('LiveAiClient: Gemini API response received', [
                    'model' => $model,
                    'status' => $response->status(),
                    'has_body' => $response->body() !== '',
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
                Log::error('LiveAiClient: Gemini returned empty content part.', [
                    'model' => $model,
                    'response_json' => $response->json(),
                ]);
                $lastException = AiParseException::invalidJson('gemini', 'Empty text payload');
                continue;
            }

            Log::info('LiveAiClient: Gemini parse successful', [
                'model' => $model,
                'text_length' => strlen($textPayload),
            ]);

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
            $status === 404 => 'not_found',
            $status >= 500 => 'server_error',
            default => 'unavailable',
        };

        $detail = $this->redact((string) data_get($response->json(), 'error.message', 'HTTP '.$status));
        $logLevel = in_array($errorType, ['rate_limit'], true) ? 'warning' : 'error';
        Log::{$logLevel}('LiveAiClient: AI ping received error status.', [
            'provider' => $provider,
            'status' => $status,
            'error_type' => $errorType,
            'error_detail' => $detail,
        ]);

        // 429 = key hợp lệ nhưng hết quota/rate-limit → vẫn coi là "kết nối được"
        // để không chặn hoàn toàn người dùng; thực tế parse sẽ thất bại riêng.
        $okForPing = $errorType === 'rate_limit';

        $userMessage = match ($errorType) {
            'authentication' => 'Khóa API không hợp lệ hoặc chưa được kích hoạt quyền truy cập.',
            'rate_limit' => 'API key hợp lệ nhưng đã hết quota hoặc đạt giới hạn lượt gọi. Vui lòng đợi rồi thử lại.',
            'not_found' => 'Model AI không tìm thấy. Vui lòng kiểm tra cấu hình AI_PROVIDER / model.',
            'server_error' => 'Máy chủ AI đang gặp sự cố (HTTP '.$status.'). Vui lòng thử lại sau.',
            default => 'Không thể kết nối với AI. Vui lòng thử lại hoặc nhập thủ công.',
        };

        return [
            'ok' => $okForPing,
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
        $responseJson = $response->json();

        // Groq và nhiều provider dùng cả error.message lẫn error.error.message
        $errDetail = $this->redact(
            (string) (
                data_get($responseJson, 'error.message')
                ?? data_get($responseJson, 'error.error.message')
                ?? data_get($responseJson, 'message')
                ?? ('HTTP '.$status)
            )
        );

        Log::error('LiveAiClient: '.$provider.' API responded with error status.', [
            'status' => $status,
            'error_detail' => $errDetail,
            'response_body' => $this->redact(substr($response->body(), 0, 500)),
        ]);

        if (in_array($status, [401, 403], true)) {
            throw AiParseException::authFailed($provider, $errDetail);
        }
        if ($status === 429) {
            throw AiParseException::rateLimited($provider);
        }

        throw AiParseException::unavailable($provider, $errDetail);
    }

    /**
     * Public method to get API key (redacted) for debugging purposes.
     * WARNING: Only use this for admin/debug endpoints, never expose to frontend.
     */
    public function apiKey(string $provider): ?string
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
Bạn là bộ phân tích chi tiêu cho ứng dụng "Ví Nói".
Nhiệm vụ: đọc câu tiếng Việt tự nhiên (kể cả input từ giọng nói / speech-to-text) → trả JSON giao dịch.

## CHUẨN HÓA SỐ TIỀN (áp dụng TRƯỚC khi tạo transaction)

Pipeline bắt buộc: nhận diện slang → chuẩn hóa đơn vị → trả số nguyên VND.

### Đơn vị cơ bản
- k / ka / kê / ki / kay / ca (biến thể speech-to-text của "k") = × 1.000
  Ví dụ: 10k = 10ka = 10kê = 10.000
- ngàn / nghìn / ngàn đồng / nghìn đồng = × 1.000
- triệu / triệu đồng = × 1.000.000

### Tiếng lóng
- xị / xì = 100.000 (1 xị = 100.000)
- lít = 100.000 KHI dùng như tiền lóng; KHÔNG áp dụng nếu "lít" là đơn vị thể tích (vd: "mua 2 lít xăng")
- củ = 1.000.000 (1 củ = 1.000.000; KHÔNG phải 100.000)
- tỏi = 1.000.000.000 (1 tỏi = 1 tỷ đồng; KHI dùng trong ngữ cảnh tiền/giao dịch)
- chai = 1.000.000 KHI dùng như tiền lóng; KHÔNG áp dụng nếu "chai" là vật thể (vd: "mua 3 chai nước hết 30k")
- cành = 100.000 (1 cành = 100.000)

### Số đứng một mình trong ngữ cảnh giá tiền
Chỉ áp dụng khi số đó rõ ràng là GIÁ TIỀN, không phải số lượng.
Phân biệt theo ĐỘ DÀI của số nguyên được nhập (không có đơn vị kèm theo):

  • Số có 1–2 chữ số (1–99)   → × 1.000
      1 → 1.000 | 5 → 5.000 | 10 → 10.000 | 30 → 30.000 | 99 → 99.000

  • Số có 3 chữ số (100–999)  → × 1.000  [người Việt hay nói tắt "trăm" = trăm nghìn]
      100 → 100.000 | 200 → 200.000 | 300 → 300.000 | 500 → 500.000

  • Số có 4+ chữ số (≥ 1.000) → GIỮ NGUYÊN giá trị số
      1.000 → 1.000 VND | 3.000 → 3.000 VND | 5.000 → 5.000 VND
      10.000 → 10.000 VND | 30.000 → 30.000 VND | 100.000 → 100.000 VND
      300.000 → 300.000 VND | 1.000.000 → 1.000.000 VND

BẢNG ĐỐI CHIẾU BẮT BUỘC (ghi nhớ):
  1 → 1000 | 10 → 10000 | 30 → 30000 | 99 → 99000
  100 → 100000 | 300 → 300000 | 500 → 500000
  1000 → 1000 | 3000 → 3000 | 5000 → 5000
  10000 → 10000 | 30000 → 30000 | 100000 → 100000 | 300000 → 300000

CẢNH BÁO: "ăn sáng 3000" = 3.000 VND (KHÔNG phải 3.000.000).
           "ăn sáng 30" = 30.000 VND.
           "ăn sáng 30000" = 30.000 VND (đã là giá trị đầy đủ, giữ nguyên).
           "ăn sáng 300" = 300.000 VND (trăm nghìn).

Phân biệt số lượng vs giá tiền: "mua 2 cái bánh giá 20" → 2 là số lượng, 20 là tiền = 20.000.

### Kết hợp rưỡi / nửa
- rưỡi = + 0.5 đơn vị: "1 triệu rưỡi" = 1.500.000; "1 củ rưỡi" = 1.500.000; "1 lít rưỡi" = 150.000
- nửa = 0.5 đơn vị: "nửa củ" = 500.000; "nửa triệu" = 500.000; "nửa lít" = 50.000

### Số kết hợp tự nhiên
- "2 củ 3" = 2.300.000; "2 triệu 3" = 2.300.000
- "2 củ 5" = 2.500.000; "1 củ 2" = 1.200.000
- "3 lít 5" = 350.000; "1 xị 5" = 150.000
- "8 triệu 5" = 8.500.000; "2 xị rưỡi" = 250.000

## QUY TẮC CHUNG

1. Chỉ tạo giao dịch từ số tiền thực sự có trong câu. Không bịa thêm khoản mới.
2. Phân biệt SỐ LƯỢNG và GIÁ TIỀN theo ngữ cảnh câu.
3. Ngày: dùng hôm_nay / hôm_qua được cung cấp. Mặc định = hôm_nay. Định dạng YYYY-MM-DD.
4. type: "income" (thu) hoặc "expense" (chi).
5. category: khớp chính xác tên trong danh mục cung cấp. Không chắc → "Khác" hoặc "Thu nhập khác".
6. amount: luôn là số nguyên VND đã chuẩn hóa.

## VÍ DỤ ĐẦU VÀO → ĐẦU RA ĐÚNG
- "ăn sáng 10k, cà phê 25, đổ xăng 1 xị, mua áo 2 củ" → 10000, 25000, 100000, 2000000
- "tiền trọ 3 củ" → 3000000
- "mua điện thoại 8 triệu 5" → 8500000
- "ăn hết 3 xị" → 300000
- "mua 3 chai nước hết 30k" → 3 là số lượng, amount = 30000
- "mua nhà 1 tỏi" → 1000000000; "mua đất 2 tỏi rưỡi" → 2500000000

JSON trả về (chỉ JSON, không giải thích, không chain-of-thought):
{"transactions":[{"type":"expense","amount":30000,"category":"Ăn uống","date":"YYYY-MM-DD","note":"..."}],"unresolved":[]}
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

        return "Hôm nay: {$today}. Hôm qua: {$yesterday}.\nDanh mục hợp lệ: {$catalog}.\nCâu cần phân tích: {$text}";
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

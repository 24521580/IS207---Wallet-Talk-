<?php

namespace App\Exceptions;

use RuntimeException;

class AiParseException extends RuntimeException
{
    protected string $errorType = 'unknown';
    protected ?string $developerMessage = null;

    public function __construct(string $message = '', string $errorType = 'unknown', ?string $developerMessage = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
        $this->developerMessage = $developerMessage;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getDeveloperMessage(): ?string
    {
        return $this->developerMessage;
    }

    public static function emptyInput(): self
    {
        return new self('Vui lòng nhập nội dung chi tiêu.', 'empty_input');
    }

    public static function invalidJson(?string $provider = null, ?string $detail = null): self
    {
        $dev = $provider ? "Provider [{$provider}] returned invalid JSON: {$detail}" : $detail;
        return new self('Không thể đọc kết quả AI. Vui lòng thử lại.', 'invalid_json', $dev);
    }

    public static function unavailable(?string $provider = null, ?string $detail = null): self
    {
        $dev = $provider ? "Provider [{$provider}] service unavailable: {$detail}" : $detail;
        return new self('Không thể kết nối với AI. Vui lòng thử lại hoặc nhập thủ công.', 'unavailable', $dev);
    }

    public static function timeout(?string $provider = null): self
    {
        $dev = $provider ? "Provider [{$provider}] timed out" : null;
        return new self('AI phản hồi quá lâu. Vui lòng thử lại hoặc nhập thủ công.', 'timeout', $dev);
    }

    public static function networkError(?string $provider = null, ?string $detail = null): self
    {
        $dev = $provider ? "Network error connecting to [{$provider}]: {$detail}" : $detail;
        return new self('Lỗi kết nối mạng khi gọi AI. Vui lòng kiểm tra lại đường truyền.', 'network', $dev);
    }

    public static function authFailed(string $provider, ?string $detail = null): self
    {
        $dev = "Provider [{$provider}] authentication failed: {$detail}";
        return new self('Khóa API không hợp lệ hoặc chưa được kích hoạt quyền truy cập.', 'authentication', $dev);
    }

    public static function rateLimited(string $provider): self
    {
        $dev = "Provider [{$provider}] rate limit / quota exceeded";
        return new self('Đã đạt giới hạn lượt gọi AI trong thời gian ngắn. Vui lòng đợi 1 phút và thử lại.', 'rate_limit', $dev);
    }

    public static function missingKey(?string $provider = null): self
    {
        $provText = $provider ? " cho {$provider}" : '';
        $dev = "API key{$provText} is missing or empty";
        return new self('Chưa cấu hình API key. Hãy bật DEMO_AI_MODE hoặc thêm API key vào biến môi trường Railway.', 'missing_key', $dev);
    }
}

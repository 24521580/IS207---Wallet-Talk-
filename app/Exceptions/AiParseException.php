<?php

namespace App\Exceptions;

use RuntimeException;

class AiParseException extends RuntimeException
{
    public static function emptyInput(): self
    {
        return new self('Vui lòng nhập nội dung chi tiêu.');
    }

    public static function invalidJson(): self
    {
        return new self('Không thể đọc kết quả AI. Vui lòng thử lại.');
    }

    public static function unavailable(): self
    {
        return new self('Không thể kết nối với AI. Vui lòng thử lại hoặc nhập thủ công.');
    }

    public static function timeout(): self
    {
        return new self('AI phản hồi quá lâu. Vui lòng thử lại hoặc nhập thủ công.');
    }

    public static function missingKey(): self
    {
        return new self('Chưa cấu hình API key. Hãy bật DEMO_AI_MODE hoặc thêm key vào .env.');
    }
}

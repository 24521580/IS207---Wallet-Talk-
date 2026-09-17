<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ParseExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Vui lòng nhập nội dung chi tiêu.',
            'text.min' => 'Câu mô tả quá ngắn để phân tích.',
        ];
    }
}

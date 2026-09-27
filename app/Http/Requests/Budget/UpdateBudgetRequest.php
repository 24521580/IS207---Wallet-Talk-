<?php

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // BudgetPolicy::update() kiểm tra trong controller
    }

    public function rules(): array
    {
        return [
            'limit_amount' => ['required', 'integer', 'min:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'limit_amount.required' => 'Vui lòng nhập hạn mức.',
            'limit_amount.min'      => 'Hạn mức tối thiểu là 1,000 VNĐ.',
        ];
    }
}

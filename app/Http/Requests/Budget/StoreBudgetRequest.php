<?php

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // BudgetPolicy::create() kiểm tra trong controller
    }

    public function rules(): array
    {
        return [
            'category_id'  => ['required', 'integer', 'exists:categories,id'],
            'month'        => ['required', 'integer', 'min:1', 'max:12'],
            'year'         => ['required', 'integer', 'min:2000', 'max:2100'],
            'limit_amount' => ['required', 'integer', 'min:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'  => 'Vui lòng chọn danh mục.',
            'category_id.exists'    => 'Danh mục không hợp lệ.',
            'month.required'        => 'Vui lòng chọn tháng.',
            'year.required'         => 'Vui lòng chọn năm.',
            'limit_amount.required' => 'Vui lòng nhập hạn mức.',
            'limit_amount.min'      => 'Hạn mức tối thiểu là 1,000 VNĐ.',
        ];
    }
}

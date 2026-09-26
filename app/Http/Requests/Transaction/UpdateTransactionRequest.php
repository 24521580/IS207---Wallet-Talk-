<?php

namespace App\Http\Requests\Transaction;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in([Category::TYPE_INCOME, Category::TYPE_EXPENSE])],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'transaction_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'note.required' => 'Vui lòng nhập nội dung giao dịch.',
        ];
    }
}

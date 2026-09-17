<?php

namespace App\Http\Requests\Transaction;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'transactions' => ['required', 'array', 'min:1', 'max:50'],
            'transactions.*.note' => ['required', 'string', 'max:255'],
            'transactions.*.amount' => ['required', 'integer', 'min:1'],
            'transactions.*.type' => ['required', Rule::in([Category::TYPE_INCOME, Category::TYPE_EXPENSE])],
            'transactions.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'transactions.*.transaction_date' => ['required', 'date'],
            'transactions.*.source' => ['required', Rule::in(['ai', 'manual'])],
        ];
    }

    public function messages(): array
    {
        return [
            'transactions.required' => 'Không có giao dịch nào để lưu.',
            'transactions.*.amount.min' => 'Số tiền phải lớn hơn 0.',
            'transactions.*.amount.integer' => 'Số tiền không hợp lệ.',
            'transactions.*.note.required' => 'Vui lòng nhập nội dung giao dịch.',
            'transactions.*.category_id.exists' => 'Danh mục không tồn tại.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('type', $this->input('type')))
                    ->ignore($categoryId),
            ],
            'type' => ['required', Rule::in([Category::TYPE_INCOME, Category::TYPE_EXPENSE])],
            'icon' => ['nullable', 'string', 'max:16'],
        ];
    }
}

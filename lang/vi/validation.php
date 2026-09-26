<?php

/*
|--------------------------------------------------------------------------
| Thông báo validation tiếng Việt
|--------------------------------------------------------------------------
| Ứng dụng dùng APP_LOCALE=vi nên mọi lỗi validation sẽ hiển thị tiếng Việt.
*/

return [
    'accepted' => ':attribute phải được chấp nhận.',
    'array' => ':attribute phải là một danh sách.',
    'boolean' => ':attribute chỉ nhận giá trị đúng hoặc sai.',
    'confirmed' => 'Xác nhận :attribute không khớp.',
    'date' => ':attribute không phải là ngày hợp lệ.',
    'different' => ':attribute và :other phải khác nhau.',
    'digits' => ':attribute phải gồm :digits chữ số.',
    'email' => ':attribute không hợp lệ.',
    'exists' => ':attribute không tồn tại.',
    'in' => ':attribute không hợp lệ.',
    'integer' => ':attribute phải là số nguyên.',
    'numeric' => ':attribute phải là số.',
    'regex' => ':attribute không đúng định dạng.',
    'required' => 'Vui lòng nhập :attribute.',
    'required_if' => 'Vui lòng nhập :attribute.',
    'same' => ':attribute và :other phải giống nhau.',
    'string' => ':attribute phải là chuỗi ký tự.',
    'unique' => ':attribute này đã được sử dụng.',
    'url' => ':attribute không phải là đường dẫn hợp lệ.',

    'max' => [
        'array' => ':attribute không được có nhiều hơn :max phần tử.',
        'file' => ':attribute không được lớn hơn :max KB.',
        'numeric' => ':attribute phải nhỏ hơn hoặc bằng :max.',
        'string' => ':attribute không được dài hơn :max ký tự.',
    ],
    'min' => [
        'array' => ':attribute phải có ít nhất :min phần tử.',
        'file' => ':attribute phải lớn hơn :min KB.',
        'numeric' => ':attribute phải lớn hơn hoặc bằng :min.',
        'string' => ':attribute phải có ít nhất :min ký tự.',
    ],
    'size' => [
        'array' => ':attribute phải có :size phần tử.',
        'file' => ':attribute phải có dung lượng :size KB.',
        'numeric' => ':attribute phải bằng :size.',
        'string' => ':attribute phải có :size ký tự.',
    ],
    'between' => [
        'array' => ':attribute phải có từ :min đến :max phần tử.',
        'file' => ':attribute phải có dung lượng từ :min đến :max KB.',
        'numeric' => ':attribute phải nằm trong khoảng :min đến :max.',
        'string' => ':attribute phải có từ :min đến :max ký tự.',
    ],

    'password' => [
        'letters' => ':attribute phải có ít nhất một chữ cái.',
        'mixed' => ':attribute phải có cả chữ hoa và chữ thường.',
        'numbers' => ':attribute phải có ít nhất một chữ số.',
        'symbols' => ':attribute phải có ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':attribute đã từng bị rò rỉ dữ liệu. Vui lòng chọn mật khẩu khác.',
    ],

    /*
    | Tên hiển thị của các trường.
    */
    'attributes' => [
        'name' => 'Họ tên',
        'email' => 'Email',
        'password' => 'Mật khẩu',
        'remember' => 'Ghi nhớ đăng nhập',
        'text' => 'Nội dung chi tiêu',
        'note' => 'Nội dung',
        'amount' => 'Số tiền',
        'type' => 'Loại giao dịch',
        'category_id' => 'Danh mục',
        'transaction_date' => 'Ngày giao dịch',
        'icon' => 'Icon',
        'from' => 'Ngày bắt đầu',
        'to' => 'Ngày kết thúc',
        'transactions' => 'Danh sách giao dịch',
        'transactions.*.note' => 'Nội dung',
        'transactions.*.amount' => 'Số tiền',
        'transactions.*.type' => 'Loại giao dịch',
        'transactions.*.category_id' => 'Danh mục',
        'transactions.*.transaction_date' => 'Ngày giao dịch',
        'transactions.*.source' => 'Nguồn giao dịch',
    ],
];

@extends('layouts.app')
@section('title', 'Sửa giao dịch')
@section('content')
    <h1 class="font-[Fraunces] text-3xl">Sửa giao dịch</h1>
    <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="card mt-5 grid gap-4 p-5 md:grid-cols-2">
        @csrf @method('PUT')
        <label class="grid gap-1 text-sm md:col-span-2">Nội dung
            <input class="field" name="note" value="{{ old('note', $transaction->note) }}" required>
        </label>
        <label class="grid gap-1 text-sm">Số tiền
            <input class="field" type="number" min="1" name="amount" value="{{ old('amount', $transaction->amount) }}" required>
        </label>
        <label class="grid gap-1 text-sm">Loại
            <select class="field" name="type">
                <option value="expense" @selected(old('type', $transaction->type) === 'expense')>Chi</option>
                <option value="income" @selected(old('type', $transaction->type) === 'income')>Thu</option>
            </select>
        </label>
        <label class="grid gap-1 text-sm">Danh mục
            <select class="field" name="category_id">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" data-type="{{ $category->type }}" @selected(old('category_id', $transaction->category_id) == $category->id)>
                        {{ $category->name }} ({{ $category->type === 'income' ? 'Thu' : 'Chi' }})
                    </option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm">Ngày
            <input class="field" type="date" name="transaction_date" value="{{ old('transaction_date', $transaction->transaction_date->toDateString()) }}">
        </label>
        <div class="md:col-span-2">
            <button class="btn btn-primary">Lưu thay đổi</button>
        </div>
    </form>
@endsection

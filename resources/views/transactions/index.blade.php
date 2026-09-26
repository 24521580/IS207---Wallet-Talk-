@extends('layouts.app')
@section('title', 'Lịch sử giao dịch')
@section('content')
    <h1 class="font-[Fraunces] text-3xl">Lịch sử giao dịch</h1>

    <form method="GET" class="card mt-5 grid gap-3 p-4 md:grid-cols-6">
        <input class="field md:col-span-2" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm nội dung, danh mục">
        <input class="field" type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Từ ngày">
        <input class="field" type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Đến ngày">
        <select class="field" name="category_id">
            <option value="">Danh mục</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select class="field" name="type">
            <option value="">Loại giao dịch</option>
            <option value="expense" @selected(($filters['type'] ?? '') === 'expense')>Chi</option>
            <option value="income" @selected(($filters['type'] ?? '') === 'income')>Thu</option>
        </select>
        <div class="flex flex-wrap gap-2 md:col-span-6">
            @php
                $today = now()->toDateString();
                $presets = [
                    'Hôm nay' => ['from' => $today, 'to' => $today],
                    '7 ngày' => ['from' => now()->subDays(6)->toDateString(), 'to' => $today],
                    'Tháng này' => ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()],
                    'Tháng trước' => ['from' => now()->subMonth()->startOfMonth()->toDateString(), 'to' => now()->subMonth()->endOfMonth()->toDateString()],
                ];
            @endphp
            @foreach ($presets as $label => $range)
                <a class="chip" href="{{ route('transactions.index', $range) }}">{{ $label }}</a>
            @endforeach
            <button class="btn btn-primary">Lọc</button>
            <a class="btn btn-ghost" href="{{ route('transactions.index') }}">Reset filter</a>
        </div>
    </form>

    @if ($transactions->isEmpty())
        <div class="card mt-8 px-6 py-16 text-center">
            <p class="font-[Fraunces] text-2xl">Ví của bạn đang trống.</p>
            <p class="mt-2 text-ink-soft">Hãy thử thêm giao dịch đầu tiên bằng AI.</p>
            <a href="{{ route('transactions.create') }}" class="btn btn-primary mt-6">Thêm bằng AI</a>
        </div>
    @else
        <div class="mt-5 hidden overflow-x-auto card md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-cream text-left text-ink-soft">
                    <tr>
                        <th class="px-4 py-3">Ngày</th>
                        <th class="px-4 py-3">Nội dung</th>
                        <th class="px-4 py-3">Danh mục</th>
                        <th class="px-4 py-3">Loại</th>
                        <th class="px-4 py-3">Số tiền</th>
                        <th class="px-4 py-3">Nguồn</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $item)
                        <tr class="border-t border-line">
                            <td class="px-4 py-3">{{ $item->transaction_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $item->note }}</td>
                            <td class="px-4 py-3">{{ $item->category?->name }}</td>
                            <td class="px-4 py-3">{{ $item->isExpense() ? 'Chi' : 'Thu' }}</td>
                            <td class="px-4 py-3 {{ $item->isExpense() ? 'amount-out' : 'amount-in' }}">
                                {{ $item->isExpense() ? '-' : '+' }}{{ vnd($item->amount) }}
                            </td>
                            <td class="px-4 py-3">{{ strtoupper($item->source) === 'AI' ? 'AI' : 'Manual' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <a class="text-leaf" href="{{ route('transactions.edit', $item) }}">Edit</a>
                                    <form method="POST" action="{{ route('transactions.destroy', $item) }}" onsubmit="return confirm('Xóa giao dịch này?')">
                                        @csrf @method('DELETE')
                                        <button class="text-orange-800">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-5 grid gap-3 md:hidden">
            @foreach ($transactions as $item)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ $item->note }}</p>
                            <p class="text-sm text-ink-soft">{{ $item->transaction_date->format('d/m/Y') }} · {{ $item->category?->name }} · {{ strtoupper($item->source) === 'AI' ? 'AI' : 'Manual' }}</p>
                        </div>
                        <p class="{{ $item->isExpense() ? 'amount-out' : 'amount-in' }}">{{ $item->isExpense() ? '-' : '+' }}{{ vnd($item->amount) }}</p>
                    </div>
                    <div class="mt-3 flex gap-3 text-sm">
                        <a class="text-leaf" href="{{ route('transactions.edit', $item) }}">Edit</a>
                        <form method="POST" action="{{ route('transactions.destroy', $item) }}" onsubmit="return confirm('Xóa giao dịch này?')">
                            @csrf @method('DELETE')
                            <button class="text-orange-800">Delete</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $transactions->links() }}</div>
    @endif
@endsection

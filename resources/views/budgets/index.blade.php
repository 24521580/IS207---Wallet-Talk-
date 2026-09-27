@extends('layouts.app')
@section('title', 'Hạn mức chi tiêu')
@section('content')

@php
    $monthNames = [
        1=>'Tháng 1',2=>'Tháng 2',3=>'Tháng 3',4=>'Tháng 4',
        5=>'Tháng 5',6=>'Tháng 6',7=>'Tháng 7',8=>'Tháng 8',
        9=>'Tháng 9',10=>'Tháng 10',11=>'Tháng 11',12=>'Tháng 12',
    ];
    $prevMonth = $month === 1 ? 12 : $month - 1;
    $prevYear  = $month === 1 ? $year - 1 : $year;
    $nextMonth = $month === 12 ? 1 : $month + 1;
    $nextYear  = $month === 12 ? $year + 1 : $year;
@endphp

{{-- Header + tháng navigator --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-[Fraunces] text-3xl">Hạn mức chi tiêu</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('budgets.index', ['month' => $prevMonth, 'year' => $prevYear]) }}"
           class="btn btn-ghost px-3 py-2 text-sm">&larr;</a>
        <span class="text-sm font-medium text-ink">{{ $monthNames[$month] }}/{{ $year }}</span>
        <a href="{{ route('budgets.index', ['month' => $nextMonth, 'year' => $nextYear]) }}"
           class="btn btn-ghost px-3 py-2 text-sm">&rarr;</a>
    </div>
</div>

{{-- Danh sách hạn mức hiện tại --}}
@if ($budgetRows->isEmpty())
    <div class="card mt-6 px-6 py-14 text-center">
        <p class="font-[Fraunces] text-2xl">Chưa có hạn mức nào.</p>
        <p class="mt-2 text-ink-soft">Thêm hạn mức bên dưới để theo dõi chi tiêu theo danh mục.</p>
    </div>
@else
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($budgetRows as $row)
        @php
            $pct        = $row['percent_used'];
            $isOver     = $row['over_budget'];
            $barColor   = $isOver ? 'bg-red-500' : ($pct >= 80 ? 'bg-clay' : 'bg-leaf');
            $textColor  = $isOver ? 'text-red-700 dark:text-red-400' : 'text-ink';
        @endphp
        <article class="card p-4 sm:p-5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-semibold {{ $textColor }}">{{ $row['category']->name }}</p>
                    @if ($isOver)
                        <span class="mt-0.5 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
                            Vượt hạn mức
                        </span>
                    @endif
                </div>
                {{-- Nút xóa --}}
                <form method="POST"
                      action="{{ route('budgets.destroy', $row['budget']) }}"
                      onsubmit="return confirm('Xóa hạn mức {{ $row['category']->name }}?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="text-xs text-ink-soft hover:text-orange-800"
                            title="Xóa hạn mức">✕</button>
                </form>
            </div>

            {{-- Progress bar --}}
            <div class="mt-3 h-2 w-full rounded-full bg-line">
                <div class="{{ $barColor }} h-2 rounded-full transition-all"
                     style="width: {{ min($pct, 100) }}%"></div>
            </div>

            {{-- Số liệu --}}
            <div class="mt-3 flex items-end justify-between text-sm">
                <div>
                    <p class="text-xs text-ink-soft">Đã chi</p>
                    <p class="{{ $isOver ? 'amount-out' : 'font-medium text-ink' }}">
                        {{ vnd($row['current_spending']) }}đ
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-ink-soft">Hạn mức</p>
                    <p class="font-medium text-ink">{{ vnd($row['limit_amount']) }}đ</p>
                </div>
            </div>

            @if ($isOver)
                <p class="mt-2 text-xs text-red-700 dark:text-red-400">
                    Đã vượt {{ vnd($row['current_spending'] - $row['limit_amount']) }}đ
                </p>
            @else
                <p class="mt-2 text-xs text-ink-soft">
                    Còn lại {{ vnd($row['remaining']) }}đ ({{ 100 - $pct }}%)
                </p>
            @endif

            {{-- Form sửa hạn mức inline --}}
            <form method="POST"
                  action="{{ route('budgets.update', $row['budget']) }}"
                  class="mt-4 flex gap-2">
                @csrf @method('PUT')
                <input type="number"
                       name="limit_amount"
                       value="{{ $row['limit_amount'] }}"
                       min="1000"
                       step="1000"
                       class="field min-h-0 py-1.5 text-sm"
                       aria-label="Hạn mức mới cho {{ $row['category']->name }}">
                <button type="submit" class="btn btn-ghost min-h-0 px-3 py-1.5 text-sm">Lưu</button>
            </form>
        </article>
        @endforeach
    </div>
@endif

{{-- Form thêm hạn mức mới --}}
<section class="card mt-8 p-5 sm:p-8">
    <h2 class="font-[Fraunces] text-xl">Thêm hạn mức mới</h2>
    <p class="mt-1 text-sm text-ink-soft">Thiết lập ngân sách tối đa cho một danh mục chi tiêu.</p>

    <form method="POST" action="{{ route('budgets.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf

        {{-- Hidden month/year từ navigator hiện tại --}}
        <input type="hidden" name="month" value="{{ $month }}">
        <input type="hidden" name="year"  value="{{ $year }}">

        <label class="grid gap-1 text-sm sm:col-span-2">
            Danh mục
            <select name="category_id" class="field" required>
                <option value="">— Chọn danh mục —</option>
                @foreach ($availableCategories as $cat)
                    <option value="{{ $cat->id }}"
                            {{ in_array($cat->id, $existingCatIds) ? 'disabled' : '' }}
                            @selected(old('category_id') == $cat->id)>
                        {{ $cat->name }}{{ in_array($cat->id, $existingCatIds) ? ' (đã có hạn mức)' : '' }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            Hạn mức (VNĐ)
            <input type="number"
                   name="limit_amount"
                   value="{{ old('limit_amount') }}"
                   min="1000"
                   step="1000"
                   placeholder="Ví dụ: 3000000"
                   class="field"
                   required>
        </label>

        <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full">Thêm hạn mức</button>
        </div>

        @if ($errors->any())
            <p class="text-sm text-orange-800 sm:col-span-2 lg:col-span-4">{{ $errors->first() }}</p>
        @endif
    </form>
</section>

@endsection

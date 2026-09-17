@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    <section class="overflow-hidden rounded-[1.7rem] bg-leaf px-6 py-8 text-white sm:px-10">
        <p class="text-sm text-white/70">Xin chào, {{ auth()->user()->name }}</p>
        <h1 class="mt-2 max-w-xl font-[Fraunces] text-3xl leading-tight sm:text-5xl">Quản lý chi tiêu cho người Việt.</h1>
        <p class="mt-3 max-w-xl text-white/80">Nhập những gì bạn đã chi. Ví Nói sẽ tự động phân loại và ghi lại cho bạn.</p>
        <a href="{{ route('transactions.create') }}" class="btn mt-6 bg-white text-leaf-deep">+ Thêm bằng AI</a>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Tổng thu tháng này', vnd($income), 'in'],
            ['Tổng chi tháng này', vnd($expense), 'out'],
            ['Số dư', vnd($balance), $balance >= 0 ? 'in' : 'out'],
            ['Chi tiêu hôm nay', vnd($today_expense), 'out'],
        ] as [$label, $value, $tone])
            <article class="card p-5">
                <p class="text-sm text-ink-soft">{{ $label }}</p>
                <p class="mt-2 text-2xl {{ $tone === 'in' ? 'amount-in' : 'amount-out' }}">{{ $value }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-2">
        <article class="card p-5">
            <h2 class="font-semibold">Chi theo danh mục</h2>
            <div class="mt-4 h-64"><canvas id="categoryChart"></canvas></div>
        </article>
        <article class="card p-5">
            <h2 class="font-semibold">Chi tiêu theo ngày</h2>
            <div class="mt-4 h-64"><canvas id="dailyChart"></canvas></div>
        </article>
    </section>

    <section class="mt-6 card p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold">Giao dịch gần đây</h2>
            <a class="text-sm text-leaf" href="{{ route('transactions.index') }}">Xem lịch sử</a>
        </div>
        <div class="mt-4 grid gap-3">
            @forelse ($recent as $item)
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-cream px-4 py-3">
                    <div>
                        <p class="font-medium">{{ $item->note }}</p>
                        <p class="text-sm text-ink-soft">{{ $item->transaction_date->format('d/m/Y') }} · {{ $item->category?->name }}</p>
                    </div>
                    <p class="{{ $item->isExpense() ? 'amount-out' : 'amount-in' }}">
                        {{ $item->isExpense() ? '-' : '+' }}{{ vnd($item->amount) }}
                    </p>
                </div>
            @empty
                <p class="text-ink-soft">Chưa có giao dịch.</p>
            @endforelse
        </div>
    </section>
@endsection
@push('scripts')
<script>
    // Dữ liệu chart được xuất bằng directive js an toàn cho ngữ cảnh script.
    window.VinoiCharts = {
        category: @js($category_chart),
        daily: @js($daily_chart),
    };
</script>
@vite('resources/js/dashboard-charts.js')
@endpush

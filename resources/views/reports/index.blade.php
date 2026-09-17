@extends('layouts.app')
@section('title', 'Báo cáo chi tiêu')
@section('content')
    <h1 class="font-[Fraunces] text-3xl">Báo cáo chi tiêu</h1>
    <p class="mt-2 text-sm text-ink-soft">
        Khoảng thời gian: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
    </p>

    {{-- Preset là link GET để không trùng tham số `preset` với form custom range. --}}
    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['week' => 'Tuần này', 'month' => 'Tháng này', 'quarter' => '3 tháng gần đây', 'custom' => 'Custom range'] as $key => $label)
            <a class="chip {{ $preset === $key ? 'bg-mist text-leaf-deep' : '' }}"
               href="{{ route('reports.index', ['preset' => $key, 'from' => $from, 'to' => $to]) }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($preset === 'custom')
        <form method="GET" action="{{ route('reports.index') }}" class="card mt-3 flex flex-wrap items-end gap-3 p-4">
            <input type="hidden" name="preset" value="custom">
            <label class="grid gap-1 text-sm">Từ ngày
                <input class="field" type="date" name="from" value="{{ $from }}" max="{{ now()->toDateString() }}">
            </label>
            <label class="grid gap-1 text-sm">Đến ngày
                <input class="field" type="date" name="to" value="{{ $to }}" max="{{ now()->toDateString() }}">
            </label>
            <button class="btn btn-primary">Áp dụng</button>
        </form>
    @endif

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
        <article class="card p-5"><p class="text-sm text-ink-soft">Tổng thu</p><p class="mt-2 text-2xl amount-in">{{ vnd($income) }}</p></article>
        <article class="card p-5"><p class="text-sm text-ink-soft">Tổng chi</p><p class="mt-2 text-2xl amount-out">{{ vnd($expense) }}</p></article>
        <article class="card p-5"><p class="text-sm text-ink-soft">Số dư</p><p class="mt-2 text-2xl {{ $balance >= 0 ? 'amount-in' : 'amount-out' }}">{{ vnd($balance) }}</p></article>
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-2">
        <article class="card p-5"><h2 class="font-semibold">Chi tiêu theo danh mục</h2><div class="mt-4 h-64"><canvas id="reportCategoryChart"></canvas></div></article>
        <article class="card p-5"><h2 class="font-semibold">Thu vs Chi</h2><div class="mt-4 h-64"><canvas id="reportBalanceChart"></canvas></div></article>
        <article class="card p-5 lg:col-span-2"><h2 class="font-semibold">Chi tiêu theo ngày</h2><div class="mt-4 h-72"><canvas id="reportDailyChart"></canvas></div></article>
    </section>

    <section class="card mt-6 p-5">
        <h2 class="font-semibold">Danh mục chi tiêu nổi bật</h2>
        <div class="mt-4 grid gap-3">
            @forelse ($top_categories as $row)
                <div class="rounded-2xl bg-cream px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ $row['category'] }}</p>
                            <p class="text-sm text-ink-soft">{{ $row['percentage'] }}% tổng chi</p>
                        </div>
                        <p class="amount-out">{{ vnd($row['total']) }}</p>
                    </div>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-line">
                        <span class="block h-full rounded-full bg-leaf" style="width: {{ min(100, $row['percentage']) }}%"></span>
                    </div>
                </div>
            @empty
                <p class="text-ink-soft">Chưa có dữ liệu trong khoảng thời gian này.</p>
            @endforelse
        </div>
    </section>
@endsection
@push('scripts')
<script>
    window.VinoiReport = {
        category: @js($category_chart),
        daily: @js($daily_chart),
        vs: @js($income_vs_expense),
    };
</script>
@vite('resources/js/report-charts.js')
@endpush

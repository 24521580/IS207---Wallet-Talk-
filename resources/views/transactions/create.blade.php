@extends('layouts.app')
@section('title', 'Thêm bằng AI')
@section('content')
    @if ($demoMode)
        <div class="mb-4 rounded-2xl border border-line bg-white px-4 py-3 text-sm">
            Đang chạy <strong>Demo AI Mode</strong>
        </div>
    @endif

    <section class="card p-5 sm:p-8">
        <h1 class="font-[Fraunces] text-3xl sm:text-4xl">Bạn đã chi gì hôm nay?</h1>
        <p class="mt-2 max-w-2xl text-ink-soft">Chỉ cần nhập bằng ngôn ngữ tự nhiên, Ví Nói sẽ tự động tách thành các khoản chi.</p>

        <form id="ai-form" class="mt-6">
            @csrf
            <label class="sr-only" for="ai-text">Nội dung chi tiêu</label>
            <textarea id="ai-text" name="text" rows="5" class="field min-h-36 text-base"
                placeholder="Hôm nay ăn sáng hết 30k, đổ xăng 100, chiều mua áo giảm giá 150 ngàn"></textarea>
            <p id="ai-input-error" class="mt-2 hidden text-sm text-orange-800"></p>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ([
                    'Ăn sáng 35k',
                    'Mua cà phê 45k và gửi xe 10k',
                    'Hôm qua mua sách 120k',
                    'Nhận lương 10 triệu',
                ] as $example)
                    <button type="button" class="chip example-chip">“{{ $example }}”</button>
                @endforeach
            </div>
            <button id="ai-submit" class="btn btn-primary mt-5 w-full sm:w-auto">Phân tích bằng AI</button>
            <p id="ai-loading" class="mt-3 hidden items-center gap-2 text-sm text-ink-soft">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-leaf border-t-transparent"></span>
                Ví Nói đang phân tích…
            </p>
        </form>
    </section>

    <section id="ai-result" class="mt-6 hidden">
        <div class="mb-4 rounded-2xl border border-clay/40 bg-white px-4 py-3 text-sm text-ink-soft">
            <strong class="text-ink">Vui lòng kiểm tra trước khi lưu.</strong>
            Sửa trực tiếp trên từng ô, xóa khoản sai hoặc thêm khoản thủ công.
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="ai-result-title" class="font-[Fraunces] text-2xl"></h2>
            <button type="button" id="add-manual" class="btn btn-ghost">+ Thêm khoản thủ công</button>
        </div>
        <p id="ai-status" class="mt-2 hidden text-sm text-ink-soft"></p>
        <p id="ai-unresolved" class="mt-2 hidden text-sm text-orange-800"></p>
        <div id="ai-summary" class="mt-3 hidden flex-wrap gap-2 text-sm"></div>
        <form method="POST" action="{{ route('transactions.confirm') }}" id="confirm-form" class="mt-4 grid gap-4">
            @csrf
            <div id="tx-list" class="grid gap-4"></div>
            <p id="tx-empty" class="hidden rounded-2xl border border-line bg-white px-4 py-8 text-center text-ink-soft">
                Không còn khoản nào để lưu. Vui lòng thêm khoản thủ công hoặc nhập lại câu khác.
            </p>
            <button id="confirm-submit" class="btn btn-primary w-full sm:w-auto">Xác nhận & Lưu</button>
        </form>
    </section>

    <template id="tx-card-template">
        <article class="card p-4 sm:p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-ink-soft js-index"></p>
                <span class="chip js-source-badge text-xs"></span>
            </div>
            <div class="grid gap-3 md:grid-cols-5">
                <label class="grid gap-1 text-sm md:col-span-2">Nội dung
                    <input class="field js-note" name="note" maxlength="255">
                </label>
                <label class="grid gap-1 text-sm">Số tiền
                    <input class="field js-amount" name="amount" type="number" min="1" step="1">
                </label>
                <label class="grid gap-1 text-sm">Loại
                    <select class="field js-type" name="type">
                        <option value="expense">Chi</option>
                        <option value="income">Thu</option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm">Ngày
                    <input class="field js-date" name="date" type="date">
                </label>
                <label class="grid gap-1 text-sm md:col-span-3">Danh mục
                    <select class="field js-category" name="category_id"></select>
                </label>
                <input type="hidden" class="js-source" value="ai">
                <div class="flex items-end md:col-span-2">
                    <button type="button" class="btn btn-ghost js-remove w-full">Xóa khoản này</button>
                </div>
            </div>
        </article>
    </template>
@endsection
@push('scripts')
<script>
    window.Vinoi = {
        parseUrl: @js(route('transactions.parse')),
        categories: @js($categories),
    };
</script>
@vite('resources/js/ai-parse.js')
@endpush

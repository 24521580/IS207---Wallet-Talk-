@extends('layouts.app')
@section('title', 'Thêm bằng AI')
@section('content')
    @if ($demoMode)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50/90 px-4 py-3 text-sm text-amber-900">
            <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Chế độ: <strong>Demo AI Mode</strong> (sử dụng mẫu nội bộ)</span>
            </div>
            <span class="text-xs text-amber-700">Đặt DEMO_AI_MODE=false để bật AI trực tiếp</span>
        </div>
    @elseif ($aiConfigured ?? false)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-300 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900">
            <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                <span>Trạng thái: <strong>AI Connected</strong> (Live {{ strtoupper($aiProvider ?? 'GEMINI') }})</span>
            </div>
            <span class="text-xs text-emerald-700">Đang gọi AI trực tiếp</span>
        </div>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-orange-300 bg-orange-50/90 px-4 py-3 text-sm text-orange-900">
            <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-orange-500"></span>
                <span>AI chưa cấu hình API key trên server</span>
            </div>
            <span class="text-xs text-orange-800">Thêm GEMINI_API_KEY hoặc OPENAI_API_KEY rồi redeploy, hoặc bật DEMO_AI_MODE=true</span>
        </div>
    @endif

    <section class="card p-5 sm:p-8">
        <h1 class="font-[Fraunces] text-3xl sm:text-4xl">Bạn đã chi gì hôm nay?</h1>
        <p class="mt-2 max-w-2xl text-ink-soft">Chỉ cần nhập bằng ngôn ngữ tự nhiên, Ví Nói sẽ tự động tách thành các khoản chi.</p>

        <form id="ai-form" class="mt-6">
            @csrf
            <label class="sr-only" for="ai-text">Nội dung chi tiêu</label>
            <div class="relative">
                <textarea id="ai-text" name="text" rows="5" class="field min-h-36 text-base"
                    placeholder="Hôm nay ăn sáng hết 30k, đổ xăng 100, chiều mua áo giảm giá 150 ngàn"></textarea>

                {{-- Voice interim display: chỉ hiển thị khi đang nghe, không ảnh hưởng textarea --}}
                <div id="voice-interim-wrap" class="mt-2 hidden rounded-xl border border-leaf/30 bg-leaf/5 px-3 py-2 text-sm text-ink-soft italic">
                    <span id="voice-interim-text"></span>
                    <span class="animate-pulse">…</span>
                </div>
            </div>

            {{-- Voice controls --}}
            <div id="voice-controls" class="mt-3 hidden items-center gap-3">
                <button
                    type="button"
                    id="voice-btn"
                    class="btn btn-ghost voice-btn-idle min-h-[44px] gap-2 px-4 text-sm"
                    title="Nói bằng giọng nói"
                    aria-label="Nhập bằng giọng nói"
                    aria-pressed="false"
                >
                    {{-- Microphone SVG icon --}}
                    <svg id="voice-icon-mic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="h-[18px] w-[18px] shrink-0" aria-hidden="true">
                        <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3Z"/>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                        <line x1="12" y1="19" x2="12" y2="22"/>
                        <line x1="8"  y1="22" x2="16" y2="22"/>
                    </svg>
                    {{-- Stop/wave icon — chỉ hiện khi đang nghe --}}
                    <svg id="voice-icon-stop" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="hidden h-[18px] w-[18px] shrink-0" aria-hidden="true">
                        <rect x="6" y="6" width="12" height="12" rx="2"/>
                    </svg>
                    <span id="voice-btn-label">Nói</span>
                </button>
                <p id="voice-status" class="hidden text-sm text-ink-soft"></p>
            </div>
            <p id="voice-error" class="mt-2 hidden text-sm text-orange-800"></p>
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
        parseUrl: @js(route('transactions.parse', absolute: false)),
        categories: @js($categories),
    };
</script>
@vite('resources/js/ai-parse.js')
@endpush

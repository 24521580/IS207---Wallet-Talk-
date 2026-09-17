<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ví Nói — Ví cho người Việt.</title>
    <meta name="description" content="Nhập một câu tự nhiên, Ví Nói tách thành nhiều khoản thu/chi!">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <header class="border-b border-line/80 bg-cream/90">
        <div class="page-shell flex items-center justify-between gap-3 py-3">
            <span class="flex min-h-0 items-center gap-2 font-semibold">
                <span class="grid h-9 w-9 place-items-center rounded-2xl bg-leaf text-white">V</span>
                <span>
                    Ví Nói
                    <span class="block text-xs font-normal text-ink-soft">Ví cho người Việt.</span>
                </span>
            </span>
            <span class="flex items-center gap-2">
                <a href="{{ route('login') }}" class="btn btn-ghost min-h-10 px-4 text-sm">Đăng nhập</a>
                <a href="{{ route('register') }}" class="btn btn-primary min-h-10 px-4 text-sm">Đăng ký</a>
            </span>
        </div>
    </header>

    <main class="page-shell py-10 lg:py-14">
        <section class="grid gap-8 lg:grid-cols-2 lg:items-center">
            <div>
                <p class="chip">Tài chính cá nhân</p>
                <h1 class="mt-4 font-[Fraunces] text-4xl leading-tight sm:text-5xl">Quản lý chi tiêu cho người Việt.</h1>
                <p class="mt-4 max-w-xl text-ink-soft">
                    Nhập những gì bạn đã chi, Ví Nói tự động tách và
                    phân loại giao dịch cho bạn!
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="btn btn-primary">+ Thêm bằng AI</a>
                    <a href="{{ route('register') }}" class="btn btn-ghost">Tạo tài khoản</a>
                </div>
            </div>

            {{-- Minh họa tĩnh đúng luồng thật: câu tiếng Việt → JSON có cấu trúc → người dùng xác nhận. --}}
            <div class="card p-5">
                <p class="text-sm font-medium text-ink-soft">Bạn nhập</p>
                <p class="mt-2 rounded-2xl bg-cream px-4 py-3">
                    “Hôm nay tui ăn sáng hết 30k, đổ xăng 100, chiều mua áo giảm giá 150 ngàn, không biết còn tiền không ta T_T”
                </p>
                <p class="mt-4 text-sm font-medium text-ink-soft">Ví Nói hiểu ngay!</p>
                <div class="mt-2 grid gap-2">
                    @foreach ([
                        ['Ăn sáng', 'Ăn uống', '30.000 ₫'],
                        ['Đổ xăng', 'Di chuyển', '100.000 ₫'],
                        ['Mua áo', 'Thời trang', '150.000 ₫'],
                    ] as [$note, $category, $amount])
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-line px-4 py-3">
                            <span>
                                <span class="block font-medium">{{ $note }}</span>
                                <span class="text-sm text-ink-soft">{{ $category }}</span>
                            </span>
                            <span class="amount-out">{{ $amount }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="mt-12 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['Nhập bằng ngôn ngữ tự nhiên'],
                ['AI tách thành giao dịch'],
                ['Kiểm tra và lưu'],
            ] as [$title, $description])
                <article class="card p-5">
                    <h2 class="font-semibold">{{ $title }}</h2>
                    <p class="mt-2 text-sm text-ink-soft">{{ $description }}</p>
                </article>
            @endforeach
        </section>

    </main>

    <footer class="page-shell py-8 text-sm text-ink-soft">
        Ví Nói · Đồ án Phát triển Ứng dụng Web · Laravel · MySQL · Tailwind CSS · Chart.js
    </footer>
</body>
</html>

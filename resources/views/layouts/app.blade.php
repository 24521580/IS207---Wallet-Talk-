<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ví Nói') — Ví cho người Việt.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="antialiased">
    <div class="min-h-screen pb-24 lg:pb-10">
        <header class="sticky top-0 z-40 border-b border-line/80 bg-cream/90 backdrop-blur">
            <div class="page-shell flex items-center justify-between gap-3 py-3">
                <a href="{{ route('dashboard') }}" class="flex min-h-0 items-center gap-2 font-semibold">
                    <span class="grid h-9 w-9 place-items-center rounded-2xl bg-leaf text-white">V</span>
                    <span>
                        Ví Nói
                        <span class="block text-xs font-normal text-ink-soft">Ví cho người Việt.</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-1 lg:flex">
                    @php($nav = [
                        ['dashboard', 'Dashboard'],
                        ['transactions.create', 'Thêm giao dịch'],
                        ['transactions.index', 'Lịch sử'],
                        ['reports.index', 'Báo cáo'],
                        ['profile.show', 'Profile'],
                    ])
                    @foreach ($nav as [$route, $label])
                        <a href="{{ route($route) }}"
                           class="rounded-full px-3 py-2 text-sm {{ request()->routeIs($route) ? 'bg-mist text-leaf-deep' : 'text-ink-soft hover:text-ink' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.categories.index') }}"
                           class="rounded-full px-3 py-2 text-sm {{ request()->routeIs('admin.*') ? 'bg-mist text-leaf-deep' : 'text-ink-soft hover:text-ink' }}">
                            Admin
                        </a>
                    @endif
                    @if (config('ai.demo_mode'))
                        <span class="chip border-clay/40 text-clay" title="Kết quả AI đến từ bộ phân tích mẫu">
                            Demo AI Mode
                        </span>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-ghost min-h-10 px-4 text-sm">Logout</button>
                    </form>
                </nav>

                <button type="button" id="nav-toggle" class="grid h-11 w-11 place-items-center rounded-full border border-line bg-white lg:hidden" aria-label="Mở menu">
                    ☰
                </button>
            </div>
            <div id="mobile-nav" class="hidden border-t border-line bg-cream px-4 py-3 lg:hidden">
                <div class="grid gap-1">
                    <a class="rounded-xl px-3 py-3" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="rounded-xl px-3 py-3" href="{{ route('transactions.create') }}">Thêm giao dịch</a>
                    <a class="rounded-xl px-3 py-3" href="{{ route('transactions.index') }}">Lịch sử</a>
                    <a class="rounded-xl px-3 py-3" href="{{ route('reports.index') }}">Báo cáo</a>
                    <a class="rounded-xl px-3 py-3" href="{{ route('profile.show') }}">Profile</a>
                    @if (auth()->user()->isAdmin())
                        <a class="rounded-xl px-3 py-3" href="{{ route('admin.categories.index') }}">Admin</a>
                    @endif
                    @if (config('ai.demo_mode'))
                        <p class="rounded-xl bg-mist px-3 py-3 text-sm text-leaf-deep">Demo AI Mode đang bật</p>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full rounded-xl px-3 py-3 text-left">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Toast: thành công tự ẩn, lỗi giữ lại cho tới khi người dùng đóng. --}}
        <div class="pointer-events-none fixed inset-x-4 top-20 z-50 grid justify-items-center gap-2 sm:inset-x-auto sm:right-6 sm:justify-items-end">
            @if (session('status'))
                <div data-toast="success" role="status" class="toast toast-success">
                    <span>{{ session('status') }}</span>
                    <button type="button" data-toast-close class="text-leaf-deep/60 hover:text-leaf-deep" aria-label="Đóng">✕</button>
                </div>
            @endif
            @if ($errors->any())
                <div data-toast="error" role="alert" class="toast toast-error">
                    <span>{{ $errors->first() }}</span>
                    <button type="button" data-toast-close class="text-orange-900/60 hover:text-orange-900" aria-label="Đóng">✕</button>
                </div>
            @endif
        </div>

        <main class="page-shell py-6 lg:py-8">
            @yield('content')
        </main>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t border-line bg-cream/95 backdrop-blur lg:hidden">
        <a class="grid place-items-center py-2 text-xs {{ request()->routeIs('dashboard') ? 'text-leaf-deep' : 'text-ink-soft' }}" href="{{ route('dashboard') }}">Tổng quan</a>
        <a class="grid place-items-center py-2 text-xs {{ request()->routeIs('transactions.create') ? 'text-leaf-deep' : 'text-ink-soft' }}" href="{{ route('transactions.create') }}">+ AI</a>
        <a class="grid place-items-center py-2 text-xs {{ request()->routeIs('transactions.index') ? 'text-leaf-deep' : 'text-ink-soft' }}" href="{{ route('transactions.index') }}">Lịch sử</a>
        <a class="grid place-items-center py-2 text-xs {{ request()->routeIs('reports.index') ? 'text-leaf-deep' : 'text-ink-soft' }}" href="{{ route('reports.index') }}">Báo cáo</a>
    </nav>
    @stack('scripts')
</body>
</html>

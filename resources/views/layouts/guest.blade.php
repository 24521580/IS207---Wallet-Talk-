<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — Ví Nói</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div class="mx-auto grid min-h-screen max-w-6xl lg:grid-cols-2">
        <section class="hidden flex-col justify-between bg-leaf px-10 py-12 text-white lg:flex">
            <div>
                <p class="text-sm uppercase tracking-[0.2em] text-white/70">Ví Nói</p>
                <h1 class="mt-6 font-[Fraunces] text-5xl leading-tight">Bạn nói,<br>Ví Nói ghi.</h1>
                <p class="mt-4 max-w-md text-white/80">Nhập một câu tiếng Việt. AI tách thành nhiều khoản thu/chi. Bạn kiểm tra, rồi mới lưu.</p>
            </div>
            <p class="text-sm text-white/70">Quản lý chi tiêu cho người Việt — không phải chatbot.</p>
        </section>
        <section class="flex items-center px-5 py-10 sm:px-10">
            <div class="mx-auto w-full max-w-md">
                @yield('content')
            </div>
        </section>
    </div>
</body>
</html>

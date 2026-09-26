@extends('layouts.guest')
@section('title', 'Đăng nhập')
@section('content')
    <p class="text-sm font-medium text-leaf">Ví Nói</p>
    <h1 class="mt-2 font-[Fraunces] text-3xl">Đăng nhập</h1>

    <form method="POST" action="{{ route('login') }}" class="mt-8 grid gap-4">
        @csrf
        <label class="grid gap-1 text-sm">
            Email
            <input class="field" type="email" name="email" required>
        </label>
        <label class="grid gap-1 text-sm">
            Mật khẩu
            <input class="field" type="password" name="password" required>
        </label>
        <label class="flex min-h-0 items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="remember" class="h-4 w-4"> Ghi nhớ đăng nhập
        </label>
        @error('email') <p class="text-sm text-orange-800">{{ $message }}</p> @enderror
        <button class="btn btn-primary">Vào ví</button>
    </form>
    <p class="mt-6 text-sm text-ink-soft">Chưa có tài khoản? <a class="font-semibold text-leaf" href="{{ route('register') }}">Đăng ký</a></p>
@endsection

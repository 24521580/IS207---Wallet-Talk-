@extends('layouts.guest')
@section('title', 'Đăng ký')
@section('content')
    <p class="text-sm font-medium text-leaf">Ví Nói</p>
    <h1 class="mt-2 font-[Fraunces] text-3xl">Tạo tài khoản</h1>
    <form method="POST" action="{{ route('register') }}" class="mt-8 grid gap-4">
        @csrf
        <label class="grid gap-1 text-sm">Họ tên<input class="field" name="name" value="{{ old('name') }}" required></label>
        <label class="grid gap-1 text-sm">Email<input class="field" type="email" name="email" value="{{ old('email') }}" required></label>
        <label class="grid gap-1 text-sm">Mật khẩu<input class="field" type="password" name="password" required></label>
        <label class="grid gap-1 text-sm">Xác nhận mật khẩu<input class="field" type="password" name="password_confirmation" required></label>
        @if ($errors->any())
            <div class="text-sm text-orange-800">{{ $errors->first() }}</div>
        @endif
        <button class="btn btn-primary">Đăng ký</button>
    </form>
    <p class="mt-6 text-sm text-ink-soft">Đã có tài khoản? <a class="font-semibold text-leaf" href="{{ route('login') }}">Đăng nhập</a></p>
@endsection

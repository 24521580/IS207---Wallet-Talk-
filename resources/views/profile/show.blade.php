@extends('layouts.app')
@section('title', 'Hồ sơ')
@section('content')
    <h1 class="font-[Fraunces] text-3xl">Profile</h1>
    <form method="POST" action="{{ route('profile.update') }}" class="card mt-5 grid max-w-xl gap-4 p-5">
        @csrf @method('PUT')
        <label class="grid gap-1 text-sm">Họ tên<input class="field" name="name" value="{{ old('name', $user->name) }}"></label>
        <label class="grid gap-1 text-sm">Email<input class="field" value="{{ $user->email }}" disabled></label>
        <p class="text-sm text-ink-soft">Vai trò: {{ $user->isAdmin() ? 'Admin' : 'User' }}</p>
        <button class="btn btn-primary w-fit">Lưu</button>
    </form>
@endsection

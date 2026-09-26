@extends('layouts.app')
@section('title', 'Quản trị danh mục')
@section('content')
    <h1 class="font-[Fraunces] text-3xl">Admin / Danh mục</h1>
    <p class="mt-2 text-sm text-ink-soft">Chỉ quản lý danh mục hệ thống. Không chỉnh sửa giao dịch cá nhân của người dùng.</p>

    <section class="mt-5 grid gap-4 sm:grid-cols-3">
        <article class="card p-5"><p class="text-sm text-ink-soft">Người dùng</p><p class="mt-2 text-2xl">{{ $stats['users'] }}</p></article>
        <article class="card p-5"><p class="text-sm text-ink-soft">Giao dịch</p><p class="mt-2 text-2xl">{{ $stats['transactions'] }}</p></article>
        <article class="card p-5"><p class="text-sm text-ink-soft">Danh mục</p><p class="mt-2 text-2xl">{{ $stats['categories'] }}</p></article>
    </section>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="card mt-6 grid gap-3 p-5 md:grid-cols-4">
        @csrf
        <input class="field" name="name" placeholder="Tên danh mục" required>
        <select class="field" name="type">
            <option value="expense">Chi</option>
            <option value="income">Thu</option>
        </select>
        <input class="field" name="icon" placeholder="Icon (tuỳ chọn)">
        <button class="btn btn-primary">Thêm danh mục</button>
    </form>

    <div class="mt-6 grid gap-3">
        @foreach ($categories as $category)
            <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="card grid gap-3 p-4 md:grid-cols-5">
                @csrf @method('PUT')
                <input class="field" name="name" value="{{ $category->name }}">
                <select class="field" name="type">
                    <option value="expense" @selected($category->type === 'expense')>Chi</option>
                    <option value="income" @selected($category->type === 'income')>Thu</option>
                </select>
                <input class="field" name="icon" value="{{ $category->icon }}">
                <p class="grid place-items-center text-sm text-ink-soft">{{ $category->transactions_count }} giao dịch</p>
                <div class="flex gap-2">
                    <button class="btn btn-primary flex-1">Sửa</button>
                    <button form="delete-{{ $category->id }}" class="btn btn-ghost flex-1">Xóa</button>
                </div>
            </form>
            <form id="delete-{{ $category->id }}" method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Xóa danh mục này?')">
                @csrf @method('DELETE')
            </form>
        @endforeach
    </div>
@endsection

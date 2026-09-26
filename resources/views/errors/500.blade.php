@extends('layouts.guest')
@section('title', 'Lỗi')
@section('content')
    <h1 class="font-[Fraunces] text-3xl">Có lỗi xảy ra. Vui lòng thử lại.</h1>
    <a class="btn btn-primary mt-6" href="{{ url('/') }}">Về trang chủ</a>
@endsection

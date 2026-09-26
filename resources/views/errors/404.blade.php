@extends('layouts.guest')
@section('title', 'Không tìm thấy')
@section('content')
    <h1 class="font-[Fraunces] text-3xl">Không tìm thấy trang này.</h1>
    <a class="btn btn-primary mt-6" href="{{ url('/') }}">Về trang chủ</a>
@endsection

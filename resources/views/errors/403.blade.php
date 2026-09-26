@extends('layouts.guest')
@section('title', 'Không có quyền')
@section('content')
    <h1 class="font-[Fraunces] text-3xl">Bạn không có quyền truy cập.</h1>
    <a class="btn btn-primary mt-6" href="{{ route('dashboard') }}">Về Dashboard</a>
@endsection

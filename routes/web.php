<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/bao-cao', ReportController::class)->name('reports.index');

    Route::get('/giao-dich', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/giao-dich/them', [TransactionController::class, 'create'])->name('transactions.create');
    // Giới hạn tần suất gọi AI để tránh lạm dụng API key và spam request.
    Route::post('/giao-dich/ai', [TransactionController::class, 'parse'])
        ->middleware('throttle:15,1')
        ->name('transactions.parse');
    Route::post('/giao-dich/xac-nhan', [TransactionController::class, 'confirm'])->name('transactions.confirm');
    Route::get('/giao-dich/{transaction}/sua', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::put('/giao-dich/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/giao-dich/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::get('/ho-so', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/ho-so', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/danh-muc', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/danh-muc', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/danh-muc/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/danh-muc/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

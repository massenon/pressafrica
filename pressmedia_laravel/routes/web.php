<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
    });

    Route::middleware('auth')->name('admin.')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::get('audit', [AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit.index');
    });
});

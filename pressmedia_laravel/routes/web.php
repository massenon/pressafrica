<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
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

        Route::get('roles', [RoleController::class, 'index'])->middleware('can:users.view')->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->middleware('can:users.create')->name('roles.store');
        Route::put('roles/{role:name}', [RoleController::class, 'update'])->middleware('can:users.edit')->name('roles.update');
        Route::delete('roles/{role:name}', [RoleController::class, 'destroy'])->middleware('can:users.delete')->name('roles.destroy');
    });
});

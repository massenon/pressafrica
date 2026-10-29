<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\SetLocale;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Root: the visitor's remembered language, else the site default (French). Never Accept-Language (INV-07).
Route::get('/', function (Request $request) {
    $saved = $request->cookie('locale');

    return redirect('/'.(in_array($saved, SetLocale::LOCALES, true) ? $saved : Settings::get('defaultLang')));
});

Route::prefix('{locale}')->whereIn('locale', SetLocale::LOCALES)->middleware('locale')->group(function () {
    Route::view('/', 'public.home')->name('home');
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

        Route::get('settings', [SettingsController::class, 'edit'])->middleware('can:settings.view')->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->middleware('can:settings.edit')->name('settings.update');

        Route::get('roles', [RoleController::class, 'index'])->middleware('can:users.view')->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->middleware('can:users.create')->name('roles.store');
        Route::put('roles/{role:name}', [RoleController::class, 'update'])->middleware('can:users.edit')->name('roles.update');
        Route::delete('roles/{role:name}', [RoleController::class, 'destroy'])->middleware('can:users.delete')->name('roles.destroy');
    });
});

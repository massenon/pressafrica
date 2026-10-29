<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Site\CommentController as PublicCommentController;
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
    Route::post('articles/{slug}/comments', [PublicCommentController::class, 'store'])->middleware('throttle:comments')->name('comments.store');
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

        // Posts: fine-grained checks (own posts vs all) live in PostPolicy.
        Route::resource('posts', PostController::class)->except('show');

        Route::get('categories', [CategoryController::class, 'index'])->middleware('can:categories.view')->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('can:categories.create')->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->middleware('can:categories.edit')->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('can:categories.edit')->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('can:categories.delete')->name('categories.destroy');

        Route::get('media', [MediaController::class, 'index'])->middleware('can:media.view')->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->middleware('can:media.create')->name('media.store');
        Route::put('media/{medium}', [MediaController::class, 'update'])->middleware('can:media.edit')->name('media.update');
        Route::delete('media/{medium}', [MediaController::class, 'destroy'])->middleware('can:media.delete')->name('media.destroy');

        Route::get('comments', [CommentController::class, 'index'])->middleware('can:comments.view')->name('comments.index');
        Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');   // right checked per status
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->middleware('can:comments.delete')->name('comments.destroy');

        Route::get('tags', [TagController::class, 'index'])->middleware('can:tags.view')->name('tags.index');
        Route::post('tags', [TagController::class, 'store'])->middleware('can:tags.create')->name('tags.store');
        Route::put('tags/{tag}', [TagController::class, 'update'])->middleware('can:tags.edit')->name('tags.update');
        Route::post('tags/{tag}/merge', [TagController::class, 'merge'])->middleware('can:tags.edit')->name('tags.merge');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->middleware('can:tags.delete')->name('tags.destroy');

        Route::get('settings', [SettingsController::class, 'edit'])->middleware('can:settings.view')->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->middleware('can:settings.edit')->name('settings.update');

        Route::get('roles', [RoleController::class, 'index'])->middleware('can:users.view')->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->middleware('can:users.create')->name('roles.store');
        Route::put('roles/{role:name}', [RoleController::class, 'update'])->middleware('can:users.edit')->name('roles.update');
        Route::delete('roles/{role:name}', [RoleController::class, 'destroy'])->middleware('can:users.delete')->name('roles.destroy');
    });
});

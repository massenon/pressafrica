<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
        // Written by JS (theme) or read before routing (locale); they hold no secret, so they stay plain.
        $middleware->encryptCookies(except: ['theme', 'locale']);
        $middleware->alias(['locale' => \App\Http\Middleware\SetLocale::class, 'count' => \App\Http\Middleware\RecordPageView::class,
            'maintenance' => \App\Http\Middleware\MaintenanceMode::class, 'active' => \App\Http\Middleware\EnsureAccountActive::class]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\PageCache;
use App\Http\Middleware\PublicHeaders;
use App\Http\Middleware\RecordPageView;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
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
        $middleware->alias(['locale' => SetLocale::class, 'count' => RecordPageView::class,
            'maintenance' => MaintenanceMode::class, 'active' => EnsureAccountActive::class, 'pagecache' => PageCache::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(PublicHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

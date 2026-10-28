<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<a href="#main" class="sr-only focus:not-sr-only">Skip to content</a>
@auth
<header class="flex items-center justify-between border-b bg-white px-6 py-3">
    <a href="{{ route('admin.dashboard') }}" class="font-serif text-lg font-bold">{{ config('app.name') }}</a>
    <nav class="flex items-center gap-4 text-sm">
        @can('audit.view')<a href="{{ route('admin.audit.index') }}">Audit log</a>@endcan
        <span class="text-slate-500">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="text-red-700">Log out</button></form>
    </nav>
</header>
@endauth
<main id="main" class="mx-auto max-w-6xl p-6">@yield('content')</main>
</body>
</html>

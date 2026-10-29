<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" @include('partials.theme-attr')>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('public.home_title'))</title>
    @foreach (\App\Http\Middleware\SetLocale::LOCALES as $l)
        <link rel="alternate" hreflang="{{ $l }}" href="{{ url($l.'/'.ltrim(\Illuminate\Support\Str::after(request()->path(), app()->getLocale()), '/')) }}">
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100">
<a href="#main" class="sr-only focus:not-sr-only">{{ __('public.skip') }}</a>
<header class="flex items-center justify-between border-b px-6 py-3">
    <a href="{{ url(app()->getLocale()) }}" class="font-serif text-lg font-bold">{{ \App\Support\Settings::get('siteTitle') }}</a>
    <div class="flex items-center gap-3">
        @php($other = app()->getLocale() === 'fr' ? 'en' : 'fr')
        <a href="{{ url($other.\Illuminate\Support\Str::after('/'.request()->path(), '/'.app()->getLocale())) }}" hreflang="{{ $other }}" lang="{{ $other }}" aria-label="{{ __('public.switch_lang_label') }}">{{ __('public.switch_lang') }}</a>
        @include('partials.theme-toggle')
    </div>
</header>
<main id="main">@yield('content')</main>
</body>
</html>

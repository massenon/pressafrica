{{-- Public layout: the prototype's <head>, chrome (resources/chrome, from tools/export_chrome.py) and assets (php artisan site:assets). --}}
@php($lang = app()->getLocale())
<!doctype html>
<html lang="{{ $lang }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">
<meta name="robots" content="{{ $meta['robots'] ?? 'index,follow,max-image-preview:large' }}">
<link rel="canonical" href="{{ $meta['canonical'] }}">
@foreach ($alternates as $l => $href)
<link rel="alternate" hreflang="{{ $l }}" href="{{ $href }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $alternates['fr'] }}">
<meta property="og:site_name" content="Africa Global Press">
<meta property="og:type" content="{{ $meta['og_type'] ?? 'website' }}">
<meta property="og:title" content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta property="og:url" content="{{ $meta['canonical'] }}">
<meta property="og:locale" content="{{ __('site.og_locale') }}">
<meta property="og:locale:alternate" content="{{ __('site.og_locale_alt') }}">
@isset($meta['image'])
<meta property="og:image" content="{{ $meta['image'] }}">
<meta property="og:image:alt" content="{{ $meta['image_alt'] ?? $meta['title'] }}">
@endisset
@stack('meta')
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $meta['title'] }}">
<meta name="twitter:description" content="{{ $meta['description'] }}">
@isset($meta['image'])<meta name="twitter:image" content="{{ $meta['image'] }}">@endisset
<meta name="theme-color" content="{{ \App\Support\Settings::get('pwaTheme') }}">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="/assets/icons/favicon.ico" sizes="48x48">
<link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml" sizes="any">
<link rel="icon" href="/assets/icons/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<link rel="preload" href="/assets/fonts/Alegreya-normal-400-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/EncodeSans-normal-400-latin.woff2" as="font" type="font/woff2" crossorigin>
@stack('preload')
<link rel="stylesheet" href="/assets/css/app.min.css">
{{-- Light by default; the visitor's saved choice wins; the OS preference is never read (INV-07). --}}
<script>(function(d){try{var t=localStorage.getItem("agp-theme");d.setAttribute("data-theme",t==="dark"?"dark":"light")}catch(e){d.setAttribute("data-theme","light")}d.classList.add("js")})(document.documentElement)</script>
<script src="/assets/js/vendor/turbo.min.js" defer></script>
<meta name="turbo-prefetch" content="true">
<meta name="view-transition" content="same-origin">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebSite', '@id' => url('/').'/#website', 'url' => url("$lang/index.html"), 'name' => 'Africa Global Press', 'inLanguage' => $lang, 'publisher' => ['@id' => url('/').'/#org']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', '@id' => url('/').'/#org', 'name' => 'Africa Global Press', 'url' => url('/').'/', 'logo' => url('assets/img/site/logo-420.webp')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@stack('jsonld')
</head>
<body class="{{ $bodyClass ?? '' }}" id="top" data-lang="{{ $lang }}" data-home="/{{ $lang }}/index.html">
{!! \App\Support\Chrome::render('sprite', $lang) !!}
{!! \App\Support\Chrome::render('header', $lang, $alternates) !!}
<main id="main" tabindex="-1">
@yield('main')
</main>
{!! \App\Support\Chrome::render('footer', $lang, $alternates) !!}
<script src="/assets/js/app.js" defer></script>
<script src="/js/agp-server.js" defer></script>
</body>
</html>

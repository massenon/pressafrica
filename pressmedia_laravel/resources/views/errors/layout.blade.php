{{-- Error pages: static on purpose (no database, no chrome rendering), so they still show when the database is down. --}}
@php
    $lang = request()->segment(1) === 'en' ? 'en' : 'fr';
@endphp
<!doctype html>
<html lang="{{ $lang }}" data-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>@yield('code') · Africa Global Press</title>
<link rel="icon" href="/assets/icons/favicon.ico?v=2" sizes="48x48">
<link rel="stylesheet" href="/assets/css/app.min.css">
</head>
<body class="page-legal">
<main id="main" class="container-page" style="min-height:70vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:3rem 1rem">
<div style="max-width:640px">
<a href="/{{ $lang }}" aria-label="Africa Global Press"><img src="/assets/img/site/logo-420.webp" alt="Africa Global Press" width="180" style="height:auto;margin:0 auto 1.5rem"></a>
<p class="sec-title sec-red" style="justify-content:center"><span class="sq" aria-hidden="true"></span>@yield('code')</p>
<h1 class="page-title">@yield('title')</h1>
<p>@yield('text')</p>
<p style="margin-top:1.5rem"><a class="btn btn-primary" href="/{{ $lang }}">{{ \App\Support\Seo::byUrl('Retour à l’accueil', 'Back to the home page') }}</a>
@hasSection('search') <a class="btn btn-outline" href="/{{ $lang }}/{{ \App\Support\Seo::byUrl('recherche', 'search') }}">{{ \App\Support\Seo::byUrl('Rechercher', 'Search') }}</a>@endif</p>
</div></main>
</body></html>

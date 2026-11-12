{{-- Media sections (build_site.py landing_body(): podcasts_body, videos_body, magazine_body, directory_body). --}}
@extends('site.layout', ['bodyClass' => 'page-cat page-landing'])
@section('main')
@include('site.sections._meta')
@php
    $lang = app()->getLocale();
    $key = $category->key;
    $u = fn ($k) => __("proto.$k");
    $artAlt = fn ($p) => __('site.illustration', ['title' => $p->translation($lang)?->title]);
    $siteUrl = rtrim(url('/'), '/');
    $base = $category->url($lang);
    $icon = fn ($n, $c = 'i i-sm') => '<svg class="'.$c.'" aria-hidden="true" focusable="false"><use href="#i-'.$n.'"/></svg>';
@endphp
<div class="container-page">@include('site.partials.breadcrumb')<h1 class="page-title">{{ $t->name }}</h1>
@include('site.sections.media.'.$mediaView)
@unless (isset($latest))<div class="home-nl">@include('site.partials.nl-card')</div>@endunless
</div>
<div class="container-page">@include('site.partials.faq')</div>
@endsection

{{-- Author page: byline target, with Person structured data and the author's articles. --}}
@extends('site.layout', ['bodyClass' => 'page-cat page-thematic'])
@php($lang = app()->getLocale())
@push('jsonld')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'ProfilePage', 'url' => $meta['canonical'], 'inLanguage' => $lang, 'mainEntity' => ['@type' => 'Person', 'name' => $author->name, 'url' => \App\Support\Authors::url($author, $lang), 'jobTitle' => __('site.editorial_team'), 'description' => __('site.bio'), 'worksFor' => ['@id' => url('/').'/#org']]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush
@section('main')
<div class="container-page">@include('site.partials.breadcrumb')
<header class="author-head"><h1 class="page-title">{{ $author->name }}</h1><p class="meta">{{ __('site.editorial_team') }}</p><p class="bio-description">{{ __('site.bio') }}</p></header>
@if ($posts->isEmpty())
<p class="empty">{{ __('site.empty') }}</p>
@else
<section aria-labelledby="au-t"><h2 class="thematic-sub" id="au-t">{{ __('site.latest_pubs') }}</h2>
<div class="grid-3">@foreach ($posts as $p)<div class="grid-cell">@include('site.partials.card-grid')</div>@endforeach</div>
@include('site.partials.pager')
</section>
@endif
</div>
@endsection

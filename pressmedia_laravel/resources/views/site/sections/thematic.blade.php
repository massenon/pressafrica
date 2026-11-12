{{-- Thematic layout (build_site.py thematic_body()): strip of 6, centred feature, grid of 9 + "show more". --}}
@extends('site.layout', ['bodyClass' => 'page-cat page-thematic'])
@section('main')
@include('site.sections._meta')
@php($lang = app()->getLocale())
@php($heading = $t->heading ?: $t->name)
@if ($posts->isEmpty())
<div class="container-page">@include('site.partials.breadcrumb')<h1 class="page-title">{{ $heading }}</h1><p class="empty">{{ __('site.empty') }}</p></div>
@else
@php($feat = $posts[0])
@php($ft = $feat->translation())
<div class="container-page thematic">@include('site.partials.breadcrumb')<div class="strip">@foreach ($posts->take(6) as $p)@include('site.partials.card-mini')@endforeach</div>
<p class="watermark" aria-hidden="true" data-t="Africa Global Press"></p><h1 class="thematic-title">{{ $heading }}</h1>
<article class="feat-center reveal">@include('site.partials.thumb', ['p' => $feat, 'cls' => 'thumb-fill', 'sizes' => '(min-width:1024px) 520px, 100vw', 'eager' => true])<div class="card-ov">@include('site.partials.chips', ['post' => $feat])<h2 class="card-title"><a href="{{ $feat->url($lang) }}">{{ $ft->title }}</a></h2><p class="excerpt">{{ $ft->excerpt }}</p>@include('site.partials.meta-date', ['post' => $feat, 'avatar' => true])</div></article>
<p class="watermark" aria-hidden="true" data-t="Africa Global Press"></p><section aria-labelledby="lp-t"><h2 class="thematic-sub" id="lp-t">{{ __('site.latest_pubs') }}</h2>
<div class="grid-3" data-loadmore-grid>@foreach ($posts as $i => $p)<div class="grid-cell"{!! $i >= 9 ? ' hidden data-more' : '' !!}>@include('site.partials.card-grid')</div>@endforeach</div>
@if ($posts->count() > 9)<div class="more-wrap"><button type="button" class="btn btn-outline" data-loadmore>{{ __('site.show_more') }}</button></div>@endif
<p class="end-list"{!! $posts->count() > 9 ? ' hidden' : '' !!} data-end><svg class="i i-sm" aria-hidden="true" focusable="false"><use href="#i-info"/></svg>{{ __('site.end_list') }}</p></section></div>
@endif
<div class="container-page">@include('site.partials.faq')</div>
@endsection

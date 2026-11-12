{{-- Archive layout (build_site.py archive_body()): featured + side story, then pages of 6 rows. --}}
@extends('site.layout', ['bodyClass' => 'page-cat page-archive'])
@section('main')
@include('site.sections._meta')
@php($lang = app()->getLocale())
<div class="container-page">@include('site.partials.breadcrumb')<h1 class="page-title">{{ $t->name }}</h1>
@if ($posts->isEmpty())
<p class="empty">{{ __('site.empty') }}</p></div>
@else
@php([$feat, $side] = [$posts[0], $posts[1] ?? null])
@php($rest = $side ? $posts->slice(2)->values() : collect())
@php($pages = $rest->chunk(6)->values())
@php($ft = $feat->translation())
<div class="feat-grid"><article class="feat reveal">@include('site.partials.thumb', ['p' => $feat, 'cls' => 'thumb-feat', 'sizes' => '(min-width:1024px) 560px, 100vw', 'eager' => true])<div class="feat-body"><h2 class="feat-title"><a href="{{ $feat->url($lang) }}">{{ $ft->title }}</a></h2><p class="excerpt">{{ $ft->excerpt }}</p>@include('site.partials.meta-date', ['post' => $feat])</div></article>
@if ($side)
@php($st = $side->translation())
<article class="feat-side reveal"><time datetime="{{ $side->published_at->toDateString() }}">{{ \App\Support\Seo::date($side->published_at) }}</time><h2 class="feat-side-title"><a href="{{ $side->url($lang) }}">{{ $st->title }}</a></h2>@include('site.partials.thumb', ['p' => $side, 'cls' => 'thumb-land', 'sizes' => '(min-width:1024px) 340px, 100vw'])</article>
@endif
</div>@include('site.partials.wave')<div class="list-grid"><section aria-labelledby="lp-t"><h2 class="sr-only" id="lp-t">{{ __('site.latest_pubs') }}</h2><div data-paged>
@forelse ($pages as $n => $page)
<div class="list-page" data-page="{{ $n }}">@foreach ($page as $p)@php($pt = $p->translation())<article class="row-item reveal">@include('site.partials.thumb', ['cls' => 'thumb-row', 'sizes' => '(min-width:768px) 220px, 40vw'])<div><time datetime="{{ $p->published_at->toDateString() }}">{{ \App\Support\Seo::date($p->published_at) }}</time><h3 class="row-title"><a href="{{ $p->url($lang) }}">{{ $pt->title }}</a></h3><p class="excerpt">{{ $pt->excerpt }}</p>@include('site.partials.chips', ['post' => $p])</div></article>@endforeach</div>
@empty
<div class="list-page" data-page="0"></div>
@endforelse
</div>
@if ($pages->count() > 1)
<nav class="pagination" aria-label="{{ __('site.pages') }}"><ul>@foreach ($pages as $n => $page)<li><button type="button" class="pg" data-goto="{{ $n }}" aria-label="{{ __('site.page_of') }} {{ $n + 1 }}"@if ($n === 0) aria-current="page"@endif>{{ $n + 1 }}</button></li>@endforeach<li><button type="button" class="pg pg-next" data-next-page aria-label="{{ __('site.next_page') }}"><svg class="i i-sm" aria-hidden="true" focusable="false"><use href="#i-angle-r"/></svg></button></li></ul></nav>
@endif
</section>@include('site.partials.nl-card')</div></div>
@endif
<div class="container-page">@include('site.partials.faq')</div>
@endsection

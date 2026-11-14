{{-- Crawlable previous / next links for server-paginated lists ($paginator), page-numbered with ?page=. --}}
@if ($paginator->hasPages())
@php($cur = $paginator->currentPage())
<nav class="more-wrap" aria-label="{{ __('site.pages') }}">@if ($cur > 1)<a class="btn btn-outline" rel="prev" href="{{ \App\Support\Seo::pageUrl($paginator, $cur - 1) }}">{{ __('site.newer') }}</a>@endif<span class="end-list">{{ __('site.page_of') }} {{ $cur }} / {{ $paginator->lastPage() }}</span>@if ($paginator->hasMorePages())<a class="btn btn-outline" rel="next" href="{{ \App\Support\Seo::pageUrl($paginator, $cur + 1) }}">{{ __('site.older') }}</a>@endif</nav>
@endif

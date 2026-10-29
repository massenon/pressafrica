@php($pt = $p->translation())
<a class="thumb {{ $cls }}" href="{{ $p->url(app()->getLocale()) }}" tabindex="-1" aria-hidden="true">@include('site.partials.img', ['media' => $p->cover, 'alt' => __('site.illustration', ['title' => $pt->title]), 'title' => $pt->title, 'sizes' => $sizes, 'eager' => $eager ?? false])</a>

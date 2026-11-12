@php($pt = $p->translation())
<article class="card card-text reveal">@include('site.partials.chips', ['post' => $p])<h3 class="card-title "><a href="{{ $p->url(app()->getLocale()) }}">{{ $pt->title }}</a></h3><p class="excerpt">{{ \App\Support\Seo::truncate($pt->excerpt ?? '', 110) }}</p>@include('site.partials.meta-date', ['post' => $p])</article>

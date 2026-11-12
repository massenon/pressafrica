@php($pt = $p->translation())
<article class="card card-sm reveal"><div class="thumb-wrap">@include('site.partials.thumb', ['cls' => 'thumb-land', 'sizes' => '(min-width:1024px) 300px, 50vw'])@include('site.partials.chips', ['post' => $p])</div><h3 class="card-title "><a href="{{ $p->url(app()->getLocale()) }}">{{ $pt->title }}</a></h3>@include('site.partials.meta', ['post' => $p])</article>

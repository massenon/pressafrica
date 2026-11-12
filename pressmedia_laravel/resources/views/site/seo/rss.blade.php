{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
<title>Africa Global Press</title>
<link>{{ url($lang) }}</link>
<description>{{ __('seo.desc.home') }}</description>
<language>{{ $lang }}</language>
<atom:link href="{{ url("$lang/feed.xml") }}" rel="self" type="application/rss+xml"/>
@foreach ($posts as $p)
@php($t = $p->translation($lang))
<item><title>{{ $t->title }}</title><link>{{ $p->url($lang) }}</link><guid isPermaLink="true">{{ $p->url($lang) }}</guid><pubDate>{{ $p->published_at->toRfc2822String() }}</pubDate><dc:creator>{{ $p->author?->name }}</dc:creator>@foreach ($p->sortedCategories($lang) as $c)<category>{{ $c->translation($lang)?->name }}</category>@endforeach<description>{{ $t->excerpt }}</description></item>
@endforeach
</channel>
</rss>

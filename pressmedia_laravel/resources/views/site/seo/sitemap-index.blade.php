{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($files as $loc)
<sitemap><loc>{{ $loc }}</loc>@if ($lastmod)<lastmod>{{ $lastmod }}</lastmod>@endif</sitemap>
@endforeach
</sitemapindex>

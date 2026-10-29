{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($entries as $e)
@foreach ($e['alts'] as $loc)
<url><loc>{{ $loc }}</loc>@if ($e['lastmod'])<lastmod>{{ $e['lastmod'] }}</lastmod>@endif
@foreach ($e['alts'] as $l => $href)<xhtml:link rel="alternate" hreflang="{{ $l }}" href="{{ $href }}"/>@endforeach
<xhtml:link rel="alternate" hreflang="x-default" href="{{ $e['alts']['fr'] ?? reset($e['alts']) }}"/>
@if ($e['image'])<image:image><image:loc>{{ $e['image'] }}</image:loc></image:image>@endif
</url>
@endforeach
@endforeach
</urlset>

{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
@foreach ($posts as $p)
@foreach ($p->translations as $t)
<url><loc>{{ $p->url($t->locale) }}</loc><news:news><news:publication><news:name>Africa Global Press</news:name><news:language>{{ $t->locale }}</news:language></news:publication><news:publication_date>{{ $p->published_at->toIso8601String() }}</news:publication_date><news:title>{{ $t->title }}</news:title></news:news></url>
@endforeach
@endforeach
</urlset>

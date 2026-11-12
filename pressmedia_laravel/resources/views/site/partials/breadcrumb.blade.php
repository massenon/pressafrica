<nav class="breadcrumb" aria-label="{{ __('site.breadcrumb') }}"><ol>@foreach ($crumbs as [$name, $href])<li>@if ($loop->last)<span aria-current="page">{{ $name }}</span>@else<a href="{{ $href }}">{{ $name }}</a>@endif</li>@endforeach</ol></nav>
@push('jsonld')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($crumbs)->map(fn ($c, $i) => array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]]))->all()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

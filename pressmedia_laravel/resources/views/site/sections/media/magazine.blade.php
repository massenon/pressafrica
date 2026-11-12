@php
        $cover = function ($i, $sizes) use ($lang, $u, $artAlt) {
            $p = $i->post ?? $i->posts->first();
            return view('site.partials.mag-cover', ['i' => $i, 'p' => $p, 'sizes' => $sizes, 'lang' => $lang, 'alt' => $p ? $artAlt($p) : ''])->render();
        };
        $toc = fn ($i) => '<ol class="mag-toc">'.$i->posts->map(fn ($p) => '<li><a href="'.e($p->url($lang)).'">'.e($p->translation($lang)?->title).'</a></li>')->implode('').'</ol>';
        $top = $issues->first();
        $it = $top->translation($lang);
    @endphp
    @push('jsonld')
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Periodical', 'name' => 'Africa Global Press Magazine', 'inLanguage' => $lang, 'url' => $base, 'publisher' => ['@id' => $siteUrl.'/#org'], 'hasPart' => $issues->map(fn ($i) => ['@type' => 'PublicationIssue', 'issueNumber' => (string) $i->get('number'), 'name' => $i->translation($lang)->title, 'description' => $i->translation($lang)->description, 'datePublished' => $i->published_on->toDateString(), 'url' => "$base#{$i->key}"])->values()->all()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
    <p class="pod-lead">{{ $u('mag_about') }}</p>
    <section class="mag-hero reveal" id="{{ $top->key }}" aria-labelledby="mag-top">{!! $cover($top, '(min-width:1024px) 400px, 70vw') !!}<div class="mag-info"><p class="pod-kicker">{!! $icon('fire') !!}{{ $u('mag_latest') }}</p><h2 id="mag-top">{{ $it->title }}</h2><p class="meta"><span>{{ $u('mag_issue') }} {{ $top->get('number') }}</span><span class="meta-sep" aria-hidden="true"></span><time datetime="{{ $top->published_on->toDateString() }}">{{ \App\Support\MediaPages::month($top->published_on, $lang) }}</time></p><p>{{ $it->description }}</p><h3 class="mag-sub">{{ $u('mag_contents') }}</h3>{!! $toc($top) !!}</div></section>
    @if ($issues->count() > 1)
    <section class="block" aria-labelledby="mag-all"><h2 class="sec-title sec-blue" id="mag-all"><span class="sq" aria-hidden="true"></span>{{ $u('mag_archive') }}</h2><div class="mag-grid">@foreach ($issues->slice(1) as $i)@php($it = $i->translation($lang))<article class="mag-card reveal" id="{{ $i->key }}">{!! $cover($i, '(min-width:1024px) 300px, 50vw') !!}<p class="meta"><span>{{ $u('mag_issue') }} {{ $i->get('number') }}</span><span class="meta-sep" aria-hidden="true"></span><time datetime="{{ $i->published_on->toDateString() }}">{{ \App\Support\MediaPages::month($i->published_on, $lang) }}</time></p><h3 class="card-title">{{ $it->title }}</h3><p class="excerpt">{{ $it->description }}</p><details class="mag-more"><summary>{{ $u('mag_summary') }}</summary>{!! $toc($i) !!}</details></article>@endforeach</div></section>
    @endif

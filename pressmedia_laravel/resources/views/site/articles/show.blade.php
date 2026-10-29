{{-- Article page, markup of the prototype (build_site.py build_article()). --}}
@extends('site.layout', ['bodyClass' => 'page-article'])
@php
    $lang = app()->getLocale();
    $url = $post->url($lang);
    $cat = $crumbCat;
    $catName = $cat?->translation($lang)?->name;
    $alt = $lang === 'fr' ? ($post->cover?->alt_fr ?: $t->title) : ($post->cover?->alt_en ?: $t->title);
    $other = $lang === 'fr' ? 'en' : 'fr';
    $meta = [
        'title' => \App\Support\Seo::title($t->title, $t->seo_title),
        'description' => \App\Support\Seo::description($t->seo_description, $t->excerpt, $t->body),
        'canonical' => $url,
        'og_type' => 'article',
        'image' => $post->cover?->url(),
        'image_alt' => $alt,
    ];
    $crumbs = array_values(array_filter([['Africa Global Press', url("$lang/index.html")], $cat ? [$catName, $cat->url($lang)] : null, [$t->title, $url]]));
@endphp
@push('meta')
<meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
<meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
<meta property="article:author" content="{{ $post->author?->name }}">
@foreach ($post->sortedCategories($lang) as $c)<meta property="article:section" content="{{ $c->translation($lang)?->name }}">
@endforeach
@endpush
@if ($post->cover?->srcset())
@push('preload')<link rel="preload" as="image" imagesrcset="{{ $post->cover->srcset() }}" imagesizes="(min-width:1024px) 860px, 100vw" fetchpriority="high">@endpush
@endif
@push('jsonld')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($crumbs)->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]])->all()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
<script type="application/ld+json">{!! json_encode(array_filter(['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $t->title, 'description' => $meta['description'], 'image' => $post->cover ? [$post->cover->url()] : null, 'datePublished' => $post->published_at->toIso8601String(), 'dateModified' => $post->updated_at->toIso8601String(), 'inLanguage' => $lang, 'author' => ['@type' => 'Person', 'name' => $post->author?->name], 'publisher' => ['@id' => url('/').'/#org'], 'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url], 'articleSection' => $post->sortedCategories($lang)->map(fn ($c) => $c->translation($lang)?->name)->all(), 'url' => $url,
    'commentCount' => $comments->count() ? $comments->count() + $comments->sum(fn ($c) => $c->replies->count()) : null,
    'comment' => $comments->isEmpty() ? null : $comments->map(fn ($c) => array_filter(['@type' => 'Comment', 'author' => ['@type' => 'Person', 'name' => $c->author], 'dateCreated' => $c->created_at->toIso8601String(), 'text' => $c->body,
        'comment' => $c->replies->isEmpty() ? null : $c->replies->map(fn ($r) => ['@type' => 'Comment', 'author' => $r->isStaff() ? ['@type' => 'Person', 'name' => $r->staff?->name ?? $r->author, 'worksFor' => ['@id' => url('/').'/#org']] : ['@type' => 'Person', 'name' => $r->author], 'dateCreated' => $r->created_at->toIso8601String(), 'text' => $r->body])->all()]))->all()]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('main')
<div class="read-progress" aria-hidden="true"><i data-read-progress></i></div><div class="container-page"><div class="article-layout"><div class="article-main">
<nav class="breadcrumb" aria-label="{{ __('site.breadcrumb') }}"><ol>@foreach ($crumbs as $i => [$name, $href])<li>@if ($loop->last)<span aria-current="page">{{ $name }}</span>@else<a href="{{ $href }}">{{ $name }}</a>@endif</li>@endforeach</ol></nav>
<article class="article">
<header class="article-head">@include('site.partials.chips')<h1 class="article-title">{{ $t->title }}</h1>
<div class="byline"><span class="avatar" aria-hidden="true">{{ mb_substr($post->author?->name ?? 'A', 0, 1) }}</span><div><p class="meta meta-lg"><span class="meta-by">{{ __('site.by') }} <b class="author">{{ $post->author?->name }}</b></span><span class="meta-sep" aria-hidden="true"></span><time datetime="{{ $post->published_at->toDateString() }}">{{ \App\Support\Seo::date($post->published_at) }}</time><span class="meta-sep" aria-hidden="true"></span><span>{{ __('site.min_read', ['n' => $minutes]) }}</span></p>
<p class="byline-upd">{{ __('site.updated') }} <time datetime="{{ $post->updated_at->toDateString() }}">{{ \App\Support\Seo::date($post->updated_at) }}</time></p></div></div>
{{-- Only between two real versions: not to or from an English title over a French body. --}}
@if (($o = $post->translations->firstWhere('locale', $other)) && ! $o->body_locale && ! $t->body_locale)<p class="alt-version"><a href="{{ $alternates[$other] }}" hreflang="{{ $other }}" lang="{{ $other }}">{{ __('site.read_in_other') }}</a></p>@endif
</header>
<figure class="article-hero">@include('site.partials.img', ['media' => $post->cover, 'alt' => $alt, 'title' => $t->title, 'class' => 'article-hero-img', 'sizes' => '(min-width:1024px) 860px, 100vw', 'eager' => true])</figure>
<div class="article-body"><div class="l-shared-sec-outer show-mobile"><div class="l-shared-sec" role="group" aria-label="{{ __('site.share_label') }}"><div class="l-shared-header meta-text"><svg class="i i-md" aria-hidden="true" focusable="false"><use href="#i-share"/></svg><span class="share-label">{{ __('site.share') }}</span></div><div class="l-shared-items">@include('site.partials.share', ['url' => $url, 'title' => $t->title, 'labels' => false])</div></div></div>
@if ($t->body_locale && $t->body_locale !== $lang)<p class="alt-version">{{ __('site.body_in_french') }}</p>@endif
{{-- Bodies are sanitised with Purifier when saved (INV-04), so they are printed as HTML here. --}}
@php($inArticle = trim(view('site.partials.ad', ['key' => 'in_article', 'placeholder' => false])->render()))
{{-- Ad zone "in_article": after the 3rd paragraph (prototype admin), nothing while inactive. --}}
<div class="prose"{!! $t->body_locale && $t->body_locale !== $lang ? ' lang="'.e($t->body_locale).'"' : '' !!}>{!! $inArticle ? \App\Support\AdSlots::afterParagraph($t->body, 3, '<div class="ad-in-article" data-ad-zone="in_article"><span class="ad-label">'.e(__('site.ad')).'</span>'.$inArticle.'</div>') : $t->body !!}</div></div>
<div class="e-shared-sec entry-sec"><div class="e-shared-header h4"><svg class="i i-md" aria-hidden="true" focusable="false"><use href="#i-share"/></svg><span>{{ __('site.share_article') }}</span></div><div class="rbbsl">@include('site.partials.share', ['url' => $url, 'title' => $t->title, 'labels' => true])</div></div>
<section class="usr-holder entry-sec" aria-label="{{ __('site.by') }} {{ $post->author?->name }}"><div class="ubox"><div class="ubox-header"><div class="author-info-wrap"><span class="avatar" aria-hidden="true">{{ mb_substr($post->author?->name ?? 'A', 0, 1) }}</span><div class="is-meta"><div class="nname-info meta-author"><span class="meta-label">{{ __('site.by') }}</span><b class="nice-name">{{ $post->author?->name }}</b></div><span class="author-job">{{ __('site.editorial_team') }}</span></div></div>
<div class="usocials meta-text"><span class="ef-label">{{ __('site.follow') }}</span>@foreach ([['facebook', 'Facebook', 'https://www.facebook.com/africaglobalpress'], ['x', 'X', 'https://x.com/africaglobalpress'], ['instagram', 'Instagram', 'https://www.instagram.com/africaglobalpress/']] as [$k, $n, $h])<a href="{{ $h }}" rel="noopener nofollow" target="_blank" aria-label="{{ $n }}" title="{{ $n }}"><svg class="i i-md" aria-hidden="true" focusable="false"><use href="#i-{{ $k }}"/></svg></a>@endforeach</div></div>
<p class="bio-description">{{ __('site.bio') }}</p></div></section>
@if ($prev || $next)
<nav class="entry-pagination e-pagi entry-sec mobile-hide" aria-label="{{ __('site.prev_next') }}"><div class="inner">
@foreach ([['left', $prev, 'prev', 'angle-l'], ['right', $next, 'next', 'angle-r']] as [$side, $p, $rel, $icon])
<div class="nav-el nav-{{ $side }}"{!! $p ? '' : ' aria-hidden="true"' !!}>@if ($p)<a href="{{ $p->url($lang) }}" rel="{{ $rel }}"><span class="nav-label is-meta">@if ($rel === 'prev')<svg class="i i-sm" aria-hidden="true" focusable="false"><use href="#i-{{ $icon }}"/></svg>@endif<span>{{ __('site.'.$rel) }}</span>@if ($rel === 'next')<svg class="i i-sm" aria-hidden="true" focusable="false"><use href="#i-{{ $icon }}"/></svg>@endif</span><span class="nav-inner">@include('site.partials.img', ['media' => $p->cover, 'alt' => __('site.illustration', ['title' => $p->translation($lang)->title]), 'title' => $p->translation($lang)->title, 'class' => 'e-pagi-img', 'sizes' => '(min-width:1024px) 300px, 50vw'])<span class="e-pagi-title">{{ $p->translation($lang)->title }}</span></span></a>@endif</div>
@endforeach
</div></nav>
@endif
<aside class="newsletter-box entry-sec" aria-labelledby="nlb-t"><div class="newsletter-inner"><div class="newsletter-featured" aria-hidden="true"><svg class="nlb-ico" aria-hidden="true" focusable="false"><use href="#i-envelope"/></svg></div><div class="newsletter-content"><h2 class="newsletter-title" id="nlb-t">{{ __('site.nl_title') }}</h2><p class="newsletter-description">{{ __('site.nl_desc') }}</p></div><div class="newsletter-form"><form class="form" data-newsletter novalidate><div class="field"><label class="sr-only" for="nlb-email">{{ __('site.nl_email') }}</label><input id="nlb-email" name="email" type="email" required autocomplete="email" placeholder="{{ __('site.nl_email') }}" aria-describedby="nlb-email-err"><p class="field-err" id="nlb-email-err" role="alert" hidden>{{ __('site.nl_email_err') }}</p></div><div class="agree-to-terms"><input id="nlb-consent" name="consent" type="checkbox" required aria-describedby="nlb-consent-err"><label for="nlb-consent"><a href="/{{ $lang }}/{{ __('site.privacy_path') }}">{{ __('site.nl_consent') }}</a></label></div><p class="field-err" id="nlb-consent-err" role="alert" hidden>{{ __('site.nl_consent_err') }}</p><input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><button type="submit" class="btn btn-primary">{{ __('site.nl_submit') }}</button><p class="form-ok" role="status" hidden>{{ __('site.nl_ok') }}</p></form></div></div></aside>
</article></div>
<aside class="article-side sidebar-wrap single-sidebar has-border is-border-gray-dash" aria-label="{{ __('site.must_read') }}"><div class="asd-inner sidebar-inner">
<section class="asd-block" aria-labelledby="sd-f"><h2 class="sec-title sec-blue" id="sd-f"><span class="sq" aria-hidden="true"></span>{{ __('site.follow_us') }}</h2><ul class="asd-soc">@foreach ([['facebook', 'Facebook', 'https://www.facebook.com/africaglobalpress', 'like'], ['x', 'X', 'https://x.com/africaglobalpress', 'follow_btn'], ['pinterest', 'Pinterest', 'https://www.pinterest.com/africaglobalpress/', 'pin'], ['instagram', 'Instagram', 'https://www.instagram.com/africaglobalpress/', 'follow_btn']] as [$k, $n, $h, $verb])<li><a href="{{ $h }}" rel="noopener noreferrer" target="_blank"><svg class="i i-md" aria-hidden="true" focusable="false"><use href="#i-{{ $k }}"/></svg><b>{{ $n }}</b><span>{{ __('site.'.$verb) }}</span></a></li>@endforeach</ul></section>
@if ($mustRead->isNotEmpty())
<section class="asd-block" aria-labelledby="sd-m"><h2 class="sec-title sec-red" id="sd-m"><span class="sq" aria-hidden="true"></span>{{ __('site.must_read') }}</h2>
@foreach ($mustRead as $p)
@php($pt = $p->translation($lang))
@if ($loop->first)
<article class="asd-top"><a class="thumb thumb-land" href="{{ $p->url($lang) }}" tabindex="-1" aria-hidden="true">@include('site.partials.img', ['media' => $p->cover, 'alt' => __('site.illustration', ['title' => $pt->title]), 'title' => $pt->title, 'sizes' => '(min-width:1024px) 300px, 100vw'])</a><h3 class="card-title "><a href="{{ $p->url($lang) }}">{{ $pt->title }}</a></h3>@include('site.partials.meta', ['post' => $p])</article>
@else
<article class="asd-item"><a class="asd-thumb" href="{{ $p->url($lang) }}" tabindex="-1" aria-hidden="true">@include('site.partials.img', ['media' => $p->cover, 'alt' => __('site.illustration', ['title' => $pt->title]), 'title' => $pt->title, 'sizes' => '120px'])</a><h3 class="asd-title"><a href="{{ $p->url($lang) }}">{{ $pt->title }}</a></h3></article>
@endif
@endforeach
</section>
@endif
<div class="asd-sticky"><aside class="nl-card reveal" aria-labelledby="nlc-t"><svg class="nl-ico" aria-hidden="true" focusable="false"><use href="#i-envelope"/></svg><h2 id="nlc-t">{{ __('site.nl_card_title') }}</h2><p>{{ __('site.nl_card_text') }}</p><button type="button" class="btn btn-ink" data-open="newsletter">{{ __('site.nl_card_btn') }}</button></aside>
<div class="asd-ad" data-ad-zone="sidebar_article"><span class="ad-label">{{ __('site.ad') }}</span>@include('site.partials.ad', ['key' => 'sidebar_article'])</div></div>
</div></aside></div>
@if ($related->isNotEmpty())
<section class="block related" aria-labelledby="rel-t"><h2 class="sec-title sec-blue" id="rel-t"><span class="sq" aria-hidden="true"></span>{{ __('site.read_also') }}</h2><div class="grid-3">
@foreach ($related as $p)
@include('site.partials.card-grid')
@endforeach
</div></section>
@endif
<section class="comment-box-wrap entry-sec" aria-labelledby="cm-t"><div class="comment-box-header"><h2 class="h3" id="cm-t"><svg class="i i-md" aria-hidden="true" focusable="false"><use href="#i-comment"/></svg>{{ __('site.leave_comment') }}</h2></div>
<div class="comment-holder">
@if ($comments->isNotEmpty())
{{-- Published comments show the name and text only, never the e-mail (EX-26); newsroom answers carry a badge (EX-46). --}}
<p class="meta">{{ __('site.cm_count', ['n' => $comments->count() + $comments->sum(fn ($c) => $c->replies->count())]) }}</p>
<ol class="comment-list" aria-label="{{ __('site.comments') }}">@foreach ($comments as $c)<li class="comment" id="comment-{{ $c->id }}"><p class="meta"><b class="author">{{ $c->author }}</b><span class="meta-sep" aria-hidden="true"></span><time datetime="{{ $c->created_at->toDateString() }}">{{ \App\Support\Seo::date($c->created_at) }}</time></p><p>{{ $c->body }}</p><a class="comment-reply-link" href="{{ $url }}?reply={{ $c->id }}#respond" data-reply-to="{{ $c->id }}" data-reply-name="{{ $c->author }}" rel="nofollow">{{ __('site.cm_reply') }}</a>
@if ($c->replies->isNotEmpty())<ol class="comment-replies">@foreach ($c->replies as $r)<li class="comment comment-reply{{ $r->isStaff() ? ' is-staff' : '' }}" id="comment-{{ $r->id }}"><p class="meta"><b class="author">{{ $r->isStaff() ? ($r->staff?->name ?? $r->author) : $r->author }}</b>@if ($r->isStaff())<span class="chip chip-sm">{{ __('site.cm_staff') }}</span>@endif<span class="meta-sep" aria-hidden="true"></span><time datetime="{{ $r->created_at->toDateString() }}">{{ \App\Support\Seo::date($r->created_at) }}</time></p><p>{{ $r->body }}</p></li>@endforeach</ol>@endif
</li>@endforeach</ol>
@endif
<div id="respond" class="comment-respond"><form class="comment-form form" data-comment-server method="POST" action="{{ url("$lang/articles/{$t->slug}/comments") }}" novalidate>@csrf
<input type="hidden" name="ft" value="{{ \App\Support\CommentGuard::token() }}">
@php($replyComment = $replyTo ? $comments->firstWhere('id', $replyTo) : null)
<input type="hidden" name="parent_id" value="{{ $replyComment?->id }}" data-reply-field>
<p class="comment-replying" data-reply-label{!! $replyComment ? '' : ' hidden' !!}><span>{{ $replyComment ? __('site.cm_replying', ['name' => $replyComment->author]) : '' }}</span> <a href="#respond" data-reply-cancel data-template="{{ __('site.cm_replying', ['name' => ':name']) }}">{{ __('site.cm_cancel_reply') }}</a></p>
<p class="comment-notes">{{ __('site.cm_notes') }}</p>
<p class="comment-form-comment"><label for="cm-comment">{{ __('site.cm_comment') }} <span class="required" aria-hidden="true">*</span></label><textarea id="cm-comment" name="comment" rows="6" maxlength="2000" required placeholder="{{ __('site.cm_placeholder') }}"></textarea></p>
<p class="comment-form-author"><label for="cm-author">{{ __('site.cm_name') }} <span class="required" aria-hidden="true">*</span></label><input id="cm-author" name="author" type="text" maxlength="80" required autocomplete="name"></p>
<p class="comment-form-email"><label for="cm-email">{{ __('site.cm_email') }} <span class="required" aria-hidden="true">*</span></label><input id="cm-email" name="email" type="email" maxlength="100" required autocomplete="email"></p>
<p class="comment-form-consent"><input id="cm-consent" name="consent" type="checkbox" value="1" required> <label for="cm-consent">{{ __('site.cm_consent') }}</label></p>
<p class="comment-form-consent"><input id="cm-notify" name="notify" type="checkbox" value="1"> <label for="cm-notify">{{ __('site.cm_notify') }}</label></p>
<input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
<p class="form-err" role="alert" hidden>{{ __('site.cm_err') }}</p><p class="form-submit"><button type="submit" class="btn btn-primary">{{ __('site.cm_submit') }}</button></p><p class="form-ok" role="status" hidden>{{ __('public.comment_pending') }}</p></form></div></div></section>
</div>
@endsection

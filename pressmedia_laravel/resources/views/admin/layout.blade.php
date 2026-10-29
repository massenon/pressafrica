{{-- Admin console in the prototype's look (prototype_html/admin, assets/js/admin.js shell; spec EX-47). --}}
@php
    $icons = require resource_path('admin/icons.php');
    $user = auth()->user();
    $is = fn (string $pattern) => request()->routeIs($pattern);
    $pendingPosts = $user?->can('posts.publish') ? \App\Models\Post::where('status', 'pending')->count() : 0;
    $pendingComments = $user?->can('comments.view') ? \App\Models\Comment::where('status', 'pending')->count() : 0;
    // Same groups and order as the prototype sidebar; modules not built yet (analytics, newsletter, ads, menus, backups) are left out.
    $nav = [
        'Overview' => [['admin.dashboard', 'index', 'Dashboard', null, 'admin.dashboard']],
        'Newsroom' => [
            ['admin.posts.index', 'posts', 'Posts', 'posts.view', 'admin.posts.*', $pendingPosts, 'Posts pending review'],
            ['admin.categories.index', 'categories', 'Categories', 'categories.view', 'admin.categories.*'],
            ['admin.tags.index', 'tags', 'Tags', 'tags.view', 'admin.tags.*'],
            ['admin.media.index', 'media', 'Media library', 'media.view', 'admin.media.*'],
            ['admin.featured.index', 'featured', 'Featured & carousel', 'featured.view', 'admin.featured.*'],
        ],
        'Channels' => [
            [['admin.media-items.index', 'podcast'], 'podcasts', 'Podcasts', 'podcasts.view', null, 0, null, ['podcast', 'podcast_show']],
            [['admin.media-items.index', 'video'], 'videos', 'Videos', 'videos.view', null, 0, null, ['video']],
            [['admin.media-items.index', 'magazine'], 'magazine', 'Magazine', 'magazine.view', null, 0, null, ['magazine']],
            [['admin.media-items.index', 'radio'], 'radios', 'African radios', 'radios.view', null, 0, null, ['radio']],
            [['admin.media-items.index', 'tv'], 'television', 'African television', 'television.view', null, 0, null, ['tv']],
        ],
        'Audience' => [
            ['admin.comments.index', 'comments', 'Comments', 'comments.view', 'admin.comments.*', $pendingComments, 'Comments pending moderation'],
            ['admin.roles.index', 'authors', 'Authors & roles', 'users.view', 'admin.roles.*'],
        ],
        'Site' => [['admin.settings.edit', 'settings', 'Settings', 'settings.view', 'admin.settings.*']],
        'System' => [['admin.audit.index', 'audit', 'Audit log', 'audit.view', 'admin.audit.*']],
    ];
@endphp
<!doctype html>
<html lang="en" @include('partials.theme-attr')>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') - AGP Admin</title>
<link rel="icon" href="/assets/icons/favicon.ico" sizes="48x48">
<link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
<link rel="stylesheet" href="/assets/css/admin.min.css">
@stack('head')
</head>
<body class="a-body" data-page="@yield('page')">
@auth
<div class="a-shell">
<aside class="a-side" id="sidebar" aria-label="Sidebar"><a class="a-side-brand" href="{{ route('admin.dashboard') }}" wire:navigate><span class="a-logo" aria-hidden="true"><img src="/assets/icons/icon-192.png" alt="" width="44" height="44"></span><span><b>Africa Global Press</b><small>Newsroom console</small></span></a>
<nav class="a-nav" aria-label="Main navigation">
@foreach ($nav as $group => $items)
    @php($visible = array_filter($items, fn ($i) => ! $i[3] || $user->can($i[3])))
    @if ($visible)
    <h2>{{ $group }}</h2>
    @foreach ($visible as $i)
        @php($href = is_array($i[0]) ? route($i[0][0], $i[0][1]) : route($i[0]))
        @php($current = $i[4] ? $is($i[4]) : ($is('admin.media-items.*') && in_array(request()->route('type'), $i[7] ?? [], true)))
        <a href="{{ $href }}" wire:navigate @if ($current) aria-current="page" @endif>{!! $icons[$i[1]] ?? '' !!}<span>{{ $i[2] }}</span>@if (($i[5] ?? 0) > 0)<span class="a-count" title="{{ $i[6] }}" aria-label="{{ $i[5] }} {{ strtolower($i[6]) }}">{{ $i[5] }}</span>@endif</a>
    @endforeach
    @endif
@endforeach
</nav></aside>
<div class="a-scrim" aria-hidden="true" data-scrim></div>
<div class="a-main">
<header class="a-top"><button type="button" class="a-iconbtn a-menu-btn" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false" data-menu-btn>{!! $icons['top-menu'] !!}</button>
<form class="a-search" role="search" method="GET" action="{{ route('admin.posts.index') }}"><input class="a-input" type="text" name="q" role="searchbox" placeholder="Search posts…" aria-label="Search posts" autocomplete="off" value="{{ request()->routeIs('admin.posts.index') ? request('q') : '' }}"></form>
<span class="flex-1 md:hidden"></span>
@can('posts.create')<a class="a-btn a-btn-flash a-btn-sm a-new" href="{{ route('admin.posts.create') }}" aria-label="New post" wire:navigate>{!! $icons['top-plus'] !!}<span>New post</span></a>@endcan
<a class="a-btn a-btn-ghost a-btn-sm hidden md:inline-flex" href="/fr/index.html">{!! $icons['top-eye'] !!}<span>View site</span></a>
<button type="button" class="a-iconbtn" id="themeBtn" data-theme-btn aria-pressed="{{ request()->cookie('theme') === 'dark' ? 'true' : 'false' }}" aria-label="Switch theme">{!! $icons['top-theme'] !!}</button>
<div class="relative" style="position:relative"><button type="button" class="a-btn a-btn-ghost a-user-btn" aria-label="Account menu: {{ $user->name }}" aria-haspopup="true" aria-expanded="false" aria-controls="userPanel" data-user-btn><span class="a-avatar" aria-hidden="true">{{ collect(explode(' ', $user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span><span class="hidden sm:inline">{{ $user->name }}</span>{!! $icons['top-chev'] !!}</button>
<div id="userPanel" class="a-card" hidden style="position:absolute;right:0;top:52px;width:300px;max-width:calc(100vw - 32px);z-index:40"><div class="a-card-b"><p class="a-eyebrow">Signed in as</p><p class="a-serif">{{ $user->name }} • {{ ucfirst($user->getRoleNames()->first() ?? '') }}</p><p class="a-muted text-sm mb-3">{{ $user->email }}</p>
<div class="flex gap-2 flex-wrap">@can('posts.view')<a class="a-btn a-btn-sm" href="{{ route('admin.posts.index') }}" wire:navigate><span>My posts</span></a>@endcan<form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="a-btn a-btn-sm a-btn-danger" type="submit">{!! $icons['top-logout'] !!}<span>Log out</span></button></form></div></div></div></div>
</header>
<main id="main" class="a-view" tabindex="-1"><div id="view">
@if (session('status'))<div class="a-card a-card-b mb-4" role="status"><span class="a-badge ok">{{ session('status') }}</span></div>@endif
@if ($errors->any())<div class="a-card a-card-b mb-4" role="alert"><ul class="a-list">@foreach ($errors->all() as $e)<li><span class="a-badge bad">{{ $e }}</span></li>@endforeach</ul></div>@endif
@yield('content')
</div></main>
</div></div>
@else
@yield('content')
@endauth
<script src="/js/agp-admin.js" defer></script>
@livewireScripts
@stack('scripts')
</body>
</html>

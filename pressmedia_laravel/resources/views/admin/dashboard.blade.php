@extends('admin.layout')
@section('title', 'Dashboard')
@section('page', 'index')
@section('content')
@php
    $cards = [
        ['Published posts', \App\Models\Post::where('status', 'published')->count(), 'posts.view', route('admin.posts.index', ['status' => 'published'])],
        ['Pending review', \App\Models\Post::where('status', 'pending')->count(), 'posts.view', route('admin.posts.index', ['status' => 'pending'])],
        ['Scheduled', \App\Models\Post::where('status', 'scheduled')->count(), 'posts.view', route('admin.posts.index', ['status' => 'scheduled'])],
        ['Comments to moderate', \App\Models\Comment::where('status', 'pending')->count(), 'comments.view', route('admin.comments.index')],
        // Readers' questions still without a newsroom answer after 48 h (spec EX-50).
        ['Questions unanswered > 48 h', \App\Models\Comment::approved()->whereNull('parent_id')->where('created_at', '<', now()->subHours(48))->whereDoesntHave('replies', fn ($q) => $q->whereNotNull('staff_user_id'))->count(), 'comments.view', route('admin.comments.index', ['status' => 'approved'])],
    ];
@endphp
<div class="a-page-head"><div><p class="a-eyebrow">Overview</p><h1>Dashboard</h1><p>Signed in as {{ auth()->user()->email }}.</p></div></div>
<div class="a-grid2">
@foreach ($cards as [$label, $n, $perm, $href])
    @can($perm)<a class="a-card a-card-b" href="{{ $href }}" wire:navigate><p class="a-eyebrow">{{ $label }}</p><p class="text-3xl a-serif">{{ $n }}</p></a>@endcan
@endforeach
</div>
@endsection

@extends('admin.layout')
@php($t = $post->translation('fr') ?? $post->translation('en'))
@section('title', 'Statistics · '.$t?->title)
@section('page', 'posts')
@section('content')
@php($number = fn ($n) => number_format($n, 0, ',', ' '))
@php($max = max(1, collect($stats['daily'])->max(fn ($r) => $r[1])))
<div class="a-page-head"><div><p class="a-eyebrow">Post statistics</p><h1>{{ $t?->title }}</h1>
<p>{{ $post->author?->name }} · <span class="a-badge {{ $post->status === 'published' ? 'ok' : '' }}">{{ ucfirst($post->status) }}</span> · {{ $post->published_at?->format('j M Y') }} · {{ $from->format('j M Y') }} → {{ now()->format('j M Y') }}</p></div>
<div class="flex flex-wrap gap-2">@if ($post->status === 'published' && $post->url('fr'))<a class="a-btn a-btn-sm a-btn-ghost" href="{{ $post->url('fr') }}" target="_blank" rel="noopener">View on site</a>@endif @can('update', $post)<a class="a-btn a-btn-sm" href="{{ route('admin.posts.edit', $post) }}" wire:navigate>Edit</a>@endcan <a class="a-btn a-btn-sm" href="{{ route('admin.posts.index') }}" wire:navigate>All posts</a></div></div>
<form method="GET" class="a-card a-tools mb-4"><div class="a-field"><label class="a-label" for="days">Period</label><select class="a-select" id="days" name="days" onchange="this.form.requestSubmit()">@foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'] as $d => $l)<option value="{{ $d }}" @selected($days === $d)>{{ $l }}</option>@endforeach</select></div></form>
<div class="a-grid2 mb-4">
<div class="a-card a-card-b"><p class="a-eyebrow">Views in period</p><p class="text-3xl a-serif">{{ $number($stats['totals']['views']) }}</p></div>
<div class="a-card a-card-b"><p class="a-eyebrow">Daily unique visitors (summed)</p><p class="text-3xl a-serif">{{ $number($stats['totals']['visitors']) }}</p></div>
<div class="a-card a-card-b"><p class="a-eyebrow">Views since publication</p><p class="text-3xl a-serif">{{ $number($stats['totals']['all_time']) }}</p></div>
<div class="a-card a-card-b"><p class="a-eyebrow">Rank in period</p><p class="text-3xl a-serif">@if ($stats['rank']){{ $stats['rank'] }} <span class="text-base a-muted">/ {{ $stats['ranked'] }}</span>@else<span class="a-muted">–</span>@endif</p></div>
</div>
<section class="a-card mb-4"><div class="a-card-h"><h2 class="text-xl">Views per day</h2></div><div class="a-card-b">
@if ($stats['totals']['views'] > 0)<table class="a-table" aria-label="Views per day"><thead><tr><th scope="col">Day</th><th scope="col"><span class="a-vh">Bar</span></th><th scope="col" class="text-right">Views</th><th scope="col" class="text-right">Visitors</th></tr></thead><tbody>@foreach ($stats['daily'] as [$d, $n, $v])<tr><td class="whitespace-nowrap">{{ $d }}</td><td style="width:60%"><span aria-hidden="true" style="display:block;height:.6rem;border-radius:4px;background:var(--brand, #013079);width:{{ round($n / $max * 100) }}%"></span></td><td class="text-right">{{ $n }}</td><td class="text-right">{{ $v }}</td></tr>@endforeach</tbody></table>
<p class="a-hint mt-2">Best day: {{ $stats['totals']['best_day'] }} views.</p>
@else<p class="a-muted">No visit recorded in this period. Visits by staff, bots and prefetches are not counted.</p>@endif</div></section>
<div class="a-grid2">
@foreach (['referrers' => 'Referrers', 'countries' => 'Countries', 'devices' => 'Devices', 'locales' => 'Language of the page'] as $block => $title)
<section class="a-card"><div class="a-card-h"><h2 class="text-xl">{{ $title }}</h2></div><div class="a-card-b">@if ($stats[$block])<table class="a-table" aria-label="{{ $title }}"><tbody>@foreach ($stats[$block] as [$label, $n])<tr><td>{{ $label !== '' ? $label : ($block === 'referrers' ? 'Direct or unknown' : 'Unknown') }}</td><td class="text-right">{{ $n }}</td></tr>@endforeach</tbody></table>@else<p class="a-muted">Nothing yet.</p>@endif
@if ($block === 'countries')<p class="a-hint mt-2">Countries come from the host's CDN header (CF-IPCountry) when available; the log stores no IP address.</p>@endif</div></section>
@endforeach
<section class="a-card"><div class="a-card-h"><h2 class="text-xl">Comments</h2></div><div class="a-card-b"><table class="a-table" aria-label="Comments"><tbody>@foreach (['approved' => 'Published', 'pending' => 'Awaiting moderation', 'spam' => 'Spam'] as $s => $l)<tr><td>{{ $l }}</td><td class="text-right">{{ $stats['comments'][$s] ?? 0 }}</td></tr>@endforeach</tbody></table>@can('comments.view')<p class="mt-2"><a href="{{ route('admin.comments.index') }}" wire:navigate>Open the comments queue</a></p>@endcan</div></section>
</div>
@endsection

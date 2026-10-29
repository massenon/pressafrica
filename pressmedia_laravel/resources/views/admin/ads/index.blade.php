@extends('admin.layout')
@section('title', 'Ad zones')
@section('page', 'ads')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Site</p><h1>Ad zones</h1><p>Inactive zones show the "reserved ad space" placeholder of the design. Figures: last 30 days.</p></div></div>
<div class="a-card"><div class="a-tablewrap"><table class="a-table" aria-label="Ad zones"><thead><tr><th scope="col">Zone</th><th scope="col">Placement</th><th scope="col">Desktop</th><th scope="col">Mobile</th><th scope="col">Status</th><th scope="col" class="text-right">Impressions</th><th scope="col" class="text-right">Clicks</th><th scope="col" class="text-right">CTR</th><th scope="col"><span class="a-vh">Actions</span></th></tr></thead><tbody>
@foreach ($zones as $z)
@php($s = $stats[$z->id] ?? null)
<tr><td><b>{{ $z->name }}</b><span class="a-muted text-xs" style="display:block">{{ $z->key }}</span></td><td class="text-sm">{{ $z->placement }}</td>
<td><span class="a-badge {{ $z->config('desktop') ? 'ok' : '' }}">{{ $z->desktop['type'] ?? '—' }} {{ $z->desktop['size'] ?? '' }}</span></td><td><span class="a-badge {{ $z->config('mobile') ? 'ok' : '' }}">{{ $z->mobile['type'] ?? '—' }} {{ $z->mobile['size'] ?? '' }}</span></td>
<td><span class="a-badge {{ $z->active ? 'ok' : '' }}">{{ $z->active ? 'active' : 'inactive' }}</span></td>
<td class="text-right">{{ (int) ($s->impressions ?? 0) }}</td><td class="text-right">{{ (int) ($s->clicks ?? 0) }}</td><td class="text-right">{{ ($s->impressions ?? 0) ? number_format($s->clicks / $s->impressions * 100, 2) : '0.00' }} %</td>
<td class="text-right">@can('ads.edit')<a class="a-btn a-btn-sm" href="{{ route('admin.ads.edit', $z) }}" wire:navigate>Edit</a>@endcan</td></tr>
@endforeach
</tbody></table></div></div>
@endsection

@extends('admin.layout')
@section('title', 'Newsletter')
@section('page', 'newsletter')
@section('content')
@php($q = request()->only('status', 'q'))
<div class="a-page-head"><div><p class="a-eyebrow">Audience</p><h1>Newsletter</h1><p>Double opt-in: an address counts once its owner has confirmed it.</p></div>
<div class="flex flex-wrap gap-2"><a class="a-btn a-btn-sm a-btn-primary" href="{{ route('admin.campaigns.index') }}" wire:navigate>Campaigns</a><a class="a-btn a-btn-sm" href="{{ route('admin.newsletter.export', ['csv'] + $q) }}">Export CSV</a><a class="a-btn a-btn-sm" href="{{ route('admin.newsletter.export', ['json'] + $q) }}">Export JSON</a></div></div>
<div class="a-grid2 mb-4">@foreach (['confirmed' => 'ok', 'pending' => 'warn', 'unsubscribed' => ''] as $s => $b)<div class="a-card a-card-b"><p class="a-eyebrow">{{ ucfirst($s) }}</p><p class="text-3xl a-serif">{{ $counts[$s] ?? 0 }}</p></div>@endforeach</div>
@can('newsletter.create')
<form method="POST" action="{{ route('admin.newsletter.import') }}" enctype="multipart/form-data" class="a-card a-card-b mb-4">@csrf<p class="a-label">Import a CSV file (columns: email, locale)</p><div class="flex flex-wrap gap-2 items-center"><input type="file" name="file" accept=".csv,text/csv" required aria-label="CSV file"><button class="a-btn a-btn-sm" type="submit">Import</button></div><p class="a-hint mt-2">Only import addresses whose owners already consented elsewhere; they are added as confirmed.</p></form>
@endcan
<section class="a-card"><form method="GET" class="a-tools"><div class="a-field" style="flex:1"><label class="a-label" for="nq">Search</label><input class="a-input" id="nq" type="search" name="q" value="{{ $filters['q'] ?? '' }}"></div>
<div class="a-field"><label class="a-label" for="ns">Status</label><select class="a-select" id="ns" name="status" onchange="this.form.requestSubmit()"><option value="">All</option>@foreach (['pending', 'confirmed', 'unsubscribed'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div></form>
<div class="a-tablewrap"><table class="a-table" aria-label="Subscribers"><thead><tr><th scope="col">E-mail</th><th scope="col">Lang</th><th scope="col">Status</th><th scope="col">Consent</th><th scope="col">Source</th><th scope="col" class="text-right"><span class="a-vh">Actions</span></th></tr></thead><tbody>
@forelse ($subs as $s)
<tr><td>{{ $s->email }}</td><td><span class="a-badge">{{ strtoupper($s->locale) }}</span></td><td><span class="a-badge {{ ['confirmed' => 'ok', 'pending' => 'warn'][$s->status] ?? '' }}">{{ $s->status }}</span></td><td class="text-sm">{{ $s->consent_at?->format('Y-m-d H:i') }}</td><td class="text-sm">{{ $s->source }}</td>
<td class="text-right">@can('newsletter.delete')<form method="POST" action="{{ route('admin.newsletter.destroy', $s) }}" data-confirm="Delete {{ $s->email }} for good?">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan</td></tr>
@empty
<tr><td colspan="6">No subscriber.</td></tr>
@endforelse
</tbody></table></div></section>
<div class="mt-4">{{ $subs->links() }}</div>
@endsection

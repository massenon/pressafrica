@extends('admin.layout')
@section('title', 'Analytics')
@section('page', 'analytics')
@section('content')
@php($q = request()->only('days', 'source'))
<div class="a-page-head"><div><p class="a-eyebrow">Overview</p><h1>Analytics</h1><p>Source: <b>{{ $report['source'] === 'ga4' ? 'Google Analytics 4' : 'first-party visit log (no cookie)' }}</b> · {{ $from->format('j M Y') }} → {{ $to->format('j M Y') }}</p></div>
<div class="flex flex-wrap gap-2"><a class="a-btn a-btn-sm" href="{{ route('admin.analytics.export', ['csv'] + $q) }}">Export CSV</a><a class="a-btn a-btn-sm" href="{{ route('admin.analytics.export', ['json'] + $q) }}">Export JSON</a></div></div>
@if ($error)<div class="a-card a-card-b mb-4" role="alert"><span class="a-badge warn">{{ $error }}</span></div>@endif
<form method="GET" class="a-card a-tools mb-4"><div class="a-field"><label class="a-label" for="days">Period</label><select class="a-select" id="days" name="days" onchange="this.form.requestSubmit()">@foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'] as $d => $l)<option value="{{ $d }}" @selected($days === $d)>{{ $l }}</option>@endforeach</select></div>
@if ($ga4)<div class="a-field"><label class="a-label" for="source">Source</label><select class="a-select" id="source" name="source" onchange="this.form.requestSubmit()"><option value="internal" @selected($report['source'] === 'internal')>First-party log</option><option value="ga4" @selected($report['source'] === 'ga4')>Google Analytics 4</option></select></div>@endif</form>
<div class="a-grid2 mb-4">
<div class="a-card a-card-b"><p class="a-eyebrow">Page views</p><p class="text-3xl a-serif">{{ number_format($report['totals']['views'], 0, ',', ' ') }}</p></div>
<div class="a-card a-card-b"><p class="a-eyebrow">{{ $report['source'] === 'ga4' ? 'Users' : 'Daily unique visitors (summed)' }}</p><p class="text-3xl a-serif">{{ number_format($report['totals']['visitors'], 0, ',', ' ') }}</p></div>
</div>
@php($max = max(1, collect($report['daily'])->max(fn ($r) => $r[1])))
<section class="a-card mb-4"><div class="a-card-h"><h2 class="text-xl">Views per day</h2></div><div class="a-card-b">
@if ($report['daily'])<table class="a-table" aria-label="Views per day"><tbody>@foreach ($report['daily'] as [$d, $n])<tr><td class="whitespace-nowrap">{{ $d }}</td><td style="width:70%"><span aria-hidden="true" style="display:block;height:.6rem;border-radius:4px;background:var(--brand, #013079);width:{{ round($n / $max * 100) }}%"></span></td><td class="text-right">{{ $n }}</td></tr>@endforeach</tbody></table>
@else<p class="a-muted">No visit recorded in this period.</p>@endif</div></section>
<div class="a-grid2">
@foreach (['posts' => 'Top articles', 'sections' => 'Sections', 'referrers' => 'Referrers', 'countries' => 'Countries', 'devices' => 'Devices', 'searches' => 'Searches on the site'] as $block => $title)
@if ($report['source'] === 'internal' || $report[$block])
<section class="a-card"><div class="a-card-h"><h2 class="text-xl">{{ $title }}</h2></div><div class="a-card-b">@if ($report[$block])<table class="a-table" aria-label="{{ $title }}"><tbody>@foreach ($report[$block] as [$label, $n])<tr><td>{{ $label }}</td><td class="text-right">{{ $n }}</td></tr>@endforeach</tbody></table>@else<p class="a-muted">Nothing yet.</p>@endif
@if ($block === 'countries' && $report['source'] === 'internal')<p class="a-hint mt-2">Countries come from the host's CDN header (CF-IPCountry) when available; the log stores no IP address.</p>@endif</div></section>
@endif
@endforeach
</div>
@endsection

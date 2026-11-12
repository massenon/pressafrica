@extends('admin.layout')
@section('title', 'Ad zone')
@section('page', 'ads')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Ad zones</p><h1>{{ $zone->name }}</h1><p>{{ $zone->key }} · {{ $zone->placement }}</p></div></div>
<form method="POST" action="{{ route('admin.ads.update', $zone) }}">@csrf @method('PUT')
<section class="a-card a-card-b mb-4"><div class="a-grid2">
<div class="a-field"><label class="a-label" for="name">Name</label><input class="a-input" id="name" name="name" value="{{ old('name', $zone->name) }}" required></div>
<div class="a-field"><label class="a-label" for="placement">Placement</label><input class="a-input" id="placement" name="placement" value="{{ old('placement', $zone->placement) }}"></div>
</div><label class="a-check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $zone->active))><span>Active (serve the ads below)</span></label></section>
@foreach (['desktop' => 'Desktop', 'mobile' => 'Mobile'] as $d => $label)
@php($c = $zone->$d ?? [])
<section class="a-card a-card-b mb-4"><p class="a-eyebrow">{{ $label }}</p>
<label class="a-check mb-3"><input type="hidden" name="{{ $d }}[enabled]" value="0"><input type="checkbox" name="{{ $d }}[enabled]" value="1" @checked(old("$d.enabled", $c['enabled'] ?? true))><span>Serve on {{ strtolower($label) }}</span></label>
<div class="a-grid2">
<div class="a-field"><label class="a-label" for="{{ $d }}-type">Type</label><select class="a-select" id="{{ $d }}-type" name="{{ $d }}[type]">@foreach (['banner' => 'Image banner + link', 'adsense' => 'Google AdSense (after consent)', 'html' => 'Sponsor HTML code'] as $v => $l)<option value="{{ $v }}" @selected(old("$d.type", $c['type'] ?? 'banner') === $v)>{{ $l }}</option>@endforeach</select></div>
<div class="a-field"><label class="a-label" for="{{ $d }}-size">Size (e.g. 300x250)</label><input class="a-input" id="{{ $d }}-size" name="{{ $d }}[size]" value="{{ old("$d.size", $c['size'] ?? '') }}"></div>
<div class="a-field" style="grid-column:1/-1"><span class="a-label">Banner image</span><livewire:media-picker :name="$d.'[media_id]'" :value="old($d.'.media_id', $c['media_id'] ?? null)" :key="'ad-'.$d" /></div>
<div class="a-field"><label class="a-label" for="{{ $d }}-url">Banner link (https://…)</label><input class="a-input" id="{{ $d }}-url" name="{{ $d }}[url]" value="{{ old("$d.url", $c['url'] ?? '') }}"></div>
<div class="a-field"><label class="a-label" for="{{ $d }}-client">AdSense client (ca-pub-…)</label><input class="a-input" id="{{ $d }}-client" name="{{ $d }}[client]" value="{{ old("$d.client", $c['client'] ?? '') }}"></div>
<div class="a-field"><label class="a-label" for="{{ $d }}-slot">AdSense slot</label><input class="a-input" id="{{ $d }}-slot" name="{{ $d }}[slot]" value="{{ old("$d.slot", $c['slot'] ?? '') }}"></div>
<div class="a-field" style="grid-column:1/-1"><label class="a-label" for="{{ $d }}-html">Sponsor HTML (printed as is)</label><textarea class="a-textarea" id="{{ $d }}-html" name="{{ $d }}[html]" rows="3">{{ old("$d.html", $c['html'] ?? '') }}</textarea></div>
</div></section>
@endforeach
<button class="a-btn a-btn-primary" type="submit">Save</button></form>
@endsection

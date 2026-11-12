@extends('admin.layout')
@section('title', 'Personal data')
@section('page', 'privacy')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">System</p><h1>Personal data</h1><p>Access and erasure requests (GDPR). Search by e-mail address.</p></div></div>
<form method="GET" class="a-card a-tools mb-4"><div class="a-field" style="flex:1"><label class="a-label" for="pemail">E-mail address</label><input class="a-input" id="pemail" type="email" name="email" value="{{ $email }}" required></div><div class="a-field" style="align-self:end"><button class="a-btn a-btn-primary" type="submit">Search</button></div></form>
@if ($data)
@php($found = count($data['comments']) + count($data['newsletter']) + count($data['contact_messages']) + ($data['staff_account'] ? 1 : 0))
<section class="a-card a-card-b mb-4"><p class="a-eyebrow">Found for {{ $email }}</p>
<ul class="a-list"><li>{{ count($data['comments']) }} comment(s)</li><li>{{ count($data['newsletter']) }} newsletter subscription(s)</li><li>{{ count($data['contact_messages']) }} contact message(s)</li><li>{{ $data['staff_account'] ? 'A staff account ('.implode(', ', $data['staff_account']['roles']).'): erasure anonymises it, its articles stay under "former author"' : 'No staff account' }}</li></ul>
@if ($found)
<div class="flex flex-wrap gap-2 mt-3"><a class="a-btn" href="{{ route('admin.privacy.export', ['email' => $email]) }}">Export (JSON)</a></div>
<form method="POST" action="{{ route('admin.privacy.erase') }}" class="mt-4" data-confirm="Erase this person's data? This cannot be undone.">@csrf<input type="hidden" name="email" value="{{ $email }}">
<div class="a-field"><label class="a-label" for="pconfirm">Type the address again to confirm the erasure</label><input class="a-input" id="pconfirm" name="confirm" type="email" required autocomplete="off"></div>
<button class="a-btn a-btn-danger" type="submit">Erase</button><p class="a-hint mt-2">Comments are kept in their threads but anonymised; subscriptions and contact messages are deleted. The audit log is kept (security record).</p></form>
@endif
</section>
@endif
@endsection

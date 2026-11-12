@extends('admin.layout')
@section('title', 'Message')
@section('page', 'messages')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Contact messages</p><h1>{{ $m->name() }}</h1><p>{{ $m->email }}{{ $m->phone ? ' · '.$m->phone : '' }}{{ $m->organisation ? ' · '.$m->organisation : '' }}{{ $m->city || $m->country ? ' · '.trim($m->city.' '.$m->country) : '' }} · {{ strtoupper($m->locale) }} · {{ $m->created_at->format('j M Y H:i') }}</p></div>
<div class="flex flex-wrap gap-2">@can('messages.edit')@if ($m->status !== 'spam')<form method="POST" action="{{ route('admin.messages.update', $m) }}">@csrf @method('PUT')<input type="hidden" name="status" value="spam"><button class="a-btn a-btn-sm" type="submit">Mark as spam</button></form>@endif @endcan
@can('messages.delete')<form method="POST" action="{{ route('admin.messages.destroy', $m) }}" data-confirm="Delete this message?">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan</div></div>
<section class="a-card a-card-b mb-4"><p style="white-space:pre-line">{{ $m->message }}</p>@if ($m->replied_at)<p class="a-hint mt-3"><span class="a-badge ok">Replied {{ $m->replied_at->format('j M Y H:i') }}</span></p>@endif</section>
@can('messages.edit')
<form method="POST" action="{{ route('admin.messages.reply', $m) }}" class="a-card a-card-b">@csrf<label class="a-label" for="body">Reply by e-mail to {{ $m->email }}</label><textarea class="a-textarea" id="body" name="body" rows="7" required>{{ old('body') }}</textarea><button class="a-btn a-btn-primary mt-2" type="submit">Send reply as {{ auth()->user()->name }}</button></form>
@endcan
@endsection

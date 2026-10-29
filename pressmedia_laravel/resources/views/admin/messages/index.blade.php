@extends('admin.layout')
@section('title', 'Messages')
@section('page', 'messages')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Audience</p><h1>Contact messages</h1><p>Sent from the contact page; a copy goes to {{ config('mail.support') ?: 'the support inbox (SUPPORT_MAIL not set)' }}.</p></div></div>
<nav class="flex flex-wrap gap-2 mb-4" aria-label="Status"><a href="{{ route('admin.messages.index') }}" @class(['a-btn a-btn-sm', 'a-btn-primary' => ! $status])>Inbox</a>@foreach (['new', 'read', 'replied', 'spam'] as $s)<a href="{{ route('admin.messages.index', ['status' => $s]) }}" @class(['a-btn a-btn-sm', 'a-btn-primary' => $status === $s])>{{ ucfirst($s) }} ({{ $counts[$s] ?? 0 }})</a>@endforeach</nav>
<div class="a-card"><div class="a-tablewrap"><table class="a-table" aria-label="Messages"><thead><tr><th scope="col">From</th><th scope="col">Message</th><th scope="col">Status</th><th scope="col">Received</th></tr></thead><tbody>
@forelse ($messages as $m)
<tr><td><a class="ttl" href="{{ route('admin.messages.show', $m) }}" wire:navigate>{{ $m->name() }}</a><span class="a-muted text-xs" style="display:block">{{ $m->email }}{{ $m->organisation ? ' · '.$m->organisation : '' }}</span></td><td class="text-sm">{{ \Illuminate\Support\Str::limit($m->message, 110) }}</td><td><span class="a-badge {{ ['new' => 'warn', 'replied' => 'ok', 'spam' => 'bad'][$m->status] ?? '' }}">{{ $m->status }}</span></td><td class="whitespace-nowrap text-sm">{{ $m->created_at->format('j M Y H:i') }}</td></tr>
@empty
<tr><td colspan="4">No message.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $messages->links() }}</div>
@endsection

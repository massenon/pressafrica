@extends('admin.layout')
@section('title', 'Campaigns')
@section('page', 'newsletter')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Newsletter</p><h1>Campaigns</h1><p>Sent by the scheduler ({{ \App\Support\Settings::get('newsletterBatch') }} e-mails per minute) to confirmed subscribers.</p></div>
<div class="flex gap-2"><a class="a-btn" href="{{ route('admin.newsletter.index') }}" wire:navigate>Subscribers</a>@can('newsletter.create')<a class="a-btn a-btn-primary" href="{{ route('admin.campaigns.create') }}" wire:navigate>New campaign</a>@endcan</div></div>
<div class="a-card"><div class="a-tablewrap"><table class="a-table" aria-label="Campaigns"><thead><tr><th scope="col">Subject</th><th scope="col">Audience</th><th scope="col">Status</th><th scope="col">When</th><th scope="col" class="text-right">Sent / failed</th><th scope="col"><span class="a-vh">Actions</span></th></tr></thead><tbody>
@forelse ($campaigns as $c)
<tr><td><a class="ttl" href="{{ route('admin.campaigns.edit', $c) }}" wire:navigate>{{ $c->subject }}</a></td><td class="text-sm">{{ strtoupper($c->locale) }}{{ $c->topics ? ' · '.implode(', ', $c->topics) : '' }}</td>
<td><span class="a-badge {{ ['sent' => 'ok', 'sending' => 'info', 'scheduled' => 'info', 'cancelled' => 'bad'][$c->status] ?? '' }}">{{ $c->status }}</span></td>
<td class="text-sm">{{ ($c->finished_at ?? $c->scheduled_at)?->format('j M Y H:i') }}</td><td class="text-right">{{ $c->sent_count }} / {{ $c->failed_count }}</td>
<td class="text-right">@if (in_array($c->status, ['scheduled', 'sending']))@can('newsletter.publish')<form method="POST" action="{{ route('admin.campaigns.cancel', $c) }}" data-confirm="Stop this campaign?">@csrf<button class="a-btn a-btn-sm a-btn-danger" type="submit">Stop</button></form>@endcan @endif</td></tr>
@empty
<tr><td colspan="6">No campaign.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $campaigns->links() }}</div>
@endsection

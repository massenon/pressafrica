@extends('admin.layout')
@section('title', 'Staff accounts')
@section('page', 'authors')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Audience</p><h1>Staff accounts</h1><p>Who can sign in to this console, with which role. Deactivate rather than delete: articles keep their author.</p></div>
<div class="flex gap-2"><a class="a-btn" href="{{ route('admin.roles.index') }}" wire:navigate>Roles &amp; permissions</a>@can('users.create')<a class="a-btn a-btn-primary" href="{{ route('admin.users.create') }}" wire:navigate>New account</a>@endcan</div></div>
<form method="GET" class="mb-4 flex gap-2"><label class="sr-only" for="q">Search</label><input class="a-input" id="q" name="q" value="{{ $q }}" placeholder="Name or e-mail"><button class="a-btn" type="submit">Search</button></form>
<div class="a-card"><div class="a-tablewrap"><table class="a-table" aria-label="Staff accounts"><thead><tr><th scope="col">Name</th><th scope="col">E-mail</th><th scope="col">Role</th><th scope="col" class="text-right">Articles</th><th scope="col">Status</th></tr></thead><tbody>
@forelse ($users as $u)
<tr><td>@can('users.edit')<a class="ttl" href="{{ route('admin.users.edit', $u) }}" wire:navigate>{{ $u->name }}</a>@else{{ $u->name }}@endcan</td><td class="text-sm">{{ $u->email }}</td><td>{{ $u->getRoleNames()->first() ?? '—' }}</td><td class="text-right">{{ $u->posts_count }}</td>
<td>@if ($u->disabled_at)<span class="a-badge bad">Deactivated</span>@else<span class="a-badge ok">Active</span>@endif</td></tr>
@empty
<tr><td colspan="5">No account.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection

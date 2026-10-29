@extends('admin.layout')
@section('title', $u->exists ? $u->name : 'New account')
@section('page', 'authors')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow"><a href="{{ route('admin.users.index') }}" wire:navigate>Staff accounts</a></p><h1>{{ $u->exists ? $u->name : 'New account' }}</h1>@if ($u->disabled_at)<p><span class="a-badge bad">Deactivated {{ $u->disabled_at->format('j M Y') }}</span></p>@endif</div></div>
<form method="POST" action="{{ $u->exists ? route('admin.users.update', $u) : route('admin.users.store') }}">@csrf @if ($u->exists) @method('PUT') @endif
<section class="a-card a-card-b mb-4"><div class="a-grid2">
<div class="a-field"><label class="a-label" for="name">Name (shown as the article byline)</label><input class="a-input" id="name" name="name" value="{{ old('name', $u->name) }}" required maxlength="120"></div>
<div class="a-field"><label class="a-label" for="email">E-mail (sign-in)</label><input class="a-input" id="email" name="email" type="email" value="{{ old('email', $u->email) }}" required maxlength="150" autocomplete="off"></div>
<div class="a-field"><label class="a-label" for="role">Role</label><select class="a-select" id="role" name="role">@foreach ($roles as $r)<option value="{{ $r }}" @selected(old('role', $u->getRoleNames()->first() ?? 'author') === $r)>{{ $r }}</option>@endforeach</select></div>
</div></section>
<section class="a-card a-card-b mb-4"><p class="a-label">{{ $u->exists ? 'New password (leave empty to keep the current one)' : 'Password' }}</p><div class="a-grid2">
<div class="a-field"><label class="a-label" for="password">Password</label><input class="a-input" id="password" name="password" type="password" autocomplete="new-password" @required(! $u->exists)></div>
<div class="a-field"><label class="a-label" for="password_confirmation">Repeat the password</label><input class="a-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(! $u->exists)></div>
</div><p class="text-sm">At least {{ max(8, (int) \App\Support\Settings::get('minPassword')) }} characters. Changing it signs the person out everywhere.</p></section>
<button class="a-btn a-btn-primary" type="submit">{{ $u->exists ? 'Save' : 'Create account' }}</button>
</form>
@if ($u->exists && ! $u->is(auth()->user()))
<form method="POST" action="{{ route('admin.users.toggle', $u) }}" class="mt-6" data-confirm="{{ $u->disabled_at ? 'Reactivate this account?' : 'Deactivate this account? The person is signed out at once.' }}">@csrf<button class="a-btn a-btn-sm {{ $u->disabled_at ? '' : 'a-btn-danger' }}" type="submit">{{ $u->disabled_at ? 'Reactivate account' : 'Deactivate account' }}</button></form>
@endif
@endsection

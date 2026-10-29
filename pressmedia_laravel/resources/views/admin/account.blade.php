@extends('admin.layout')
@section('title', 'My account')
@section('content')
<div class="a-page-head"><div><h1>My account</h1><p>{{ auth()->user()->email }} · {{ auth()->user()->getRoleNames()->first() }}</p></div></div>
<form method="POST" action="{{ route('admin.account.update') }}">@csrf @method('PUT')
<section class="a-card a-card-b mb-4"><div class="a-field"><label class="a-label" for="name">Name (shown as the article byline)</label><input class="a-input" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="120"></div></section>
<section class="a-card a-card-b mb-4"><p class="a-label">Change the password (leave empty to keep it)</p><div class="a-grid2">
<div class="a-field"><label class="a-label" for="password">New password</label><input class="a-input" id="password" name="password" type="password" autocomplete="new-password"></div>
<div class="a-field"><label class="a-label" for="password_confirmation">Repeat the new password</label><input class="a-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
</div><p class="text-sm">At least {{ max(8, (int) \App\Support\Settings::get('minPassword')) }} characters. Your other sessions are signed out.</p></section>
<section class="a-card a-card-b mb-4"><div class="a-field"><label class="a-label" for="current_password">Current password (required to save)</label><input class="a-input" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div></section>
<button class="a-btn a-btn-primary" type="submit">Save</button>
</form>
@endsection

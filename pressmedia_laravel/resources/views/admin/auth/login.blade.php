{{-- Prototype login page (prototype_html/admin/login.html), wired to the real session login. --}}
@extends('admin.layout')
@section('title', 'Sign in')
@section('page', 'login')
@section('content')
<div class="a-login">
  <aside class="a-login-art" aria-label="Africa Global Press">
    <div class="flex items-center gap-3"><span class="a-logo" aria-hidden="true"><img src="/assets/icons/icon-192.png" alt="" width="44" height="44"></span><b class="a-serif text-2xl">Africa Global Press</b></div>
    <div><h1>The newsroom console.</h1><p>Write, review and publish across languages and formats.</p></div>
    <p class="a-eyebrow">Staff only • every sign-in is journaled</p>
  </aside>
  <main id="main" class="a-login-form"><div class="a-card a-card-b w-full" id="loginCard">
    <h2 class="text-3xl mb-1">Sign in</h2><p class="a-muted mb-4">Use your newsroom account.</p>
    @error('email')<div class="a-badge bad mb-3" role="alert">{{ $message }}</div>@enderror
    <form method="POST" action="{{ url('/admin/login') }}" novalidate>
      @csrf
      <div class="a-field"><label class="a-label" for="email">Email</label><input class="a-input" id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required aria-required="true" autofocus></div>
      <div class="a-field"><label class="a-label" for="password">Password</label><input class="a-input" id="password" name="password" type="password" autocomplete="current-password" required aria-required="true"></div>
      <label class="a-check mb-3"><input type="checkbox" name="remember" value="1"><span>Remember me</span></label>
      <button class="a-btn a-btn-primary w-full" type="submit">Sign in</button>
    </form>
    <p class="a-hint mt-4">Repeated failed attempts lock sign-in for a few minutes.</p>
  </div></main>
</div>
@endsection

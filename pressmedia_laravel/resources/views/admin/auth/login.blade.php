@extends('admin.layout')
@section('title', 'Sign in')
@section('content')
<div class="mx-auto mt-16 max-w-sm rounded-lg border bg-white p-6 shadow-sm">
    <h1 class="mb-4 text-2xl font-bold">Sign in</h1>
    @error('email')<p class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
    <form method="POST" action="{{ url('/admin/login') }}" novalidate>
        @csrf
        <label class="mb-1 block text-sm font-medium" for="email">Email</label>
        <input class="mb-3 w-full rounded border px-3 py-2" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        <label class="mb-1 block text-sm font-medium" for="password">Password</label>
        <input class="mb-3 w-full rounded border px-3 py-2" id="password" name="password" type="password" autocomplete="current-password" required>
        <label class="mb-4 flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="w-full rounded bg-slate-900 px-4 py-2 font-semibold text-white" type="submit">Sign in</button>
    </form>
</div>
@endsection

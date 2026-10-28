@extends('admin.layout')
@section('title', 'Roles & permissions')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Roles &amp; permissions</h1>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<p class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $errors->first() }}</p>@endif

<nav class="mb-4 flex flex-wrap gap-2" aria-label="Roles">
    @foreach ($roles as $r)
        <a href="{{ route('admin.roles.index', ['role' => $r->name]) }}" @class(['rounded border px-3 py-1 text-sm', 'bg-slate-900 text-white' => $current?->is($r)]) @if ($current?->is($r)) aria-current="page" @endif>{{ $r->name }} <span class="opacity-70">({{ $r->users_count }})</span></a>
    @endforeach
</nav>

@can('users.create')
<form method="POST" action="{{ route('admin.roles.store') }}" class="mb-6 flex gap-2 text-sm">
    @csrf
    <label class="sr-only" for="name">Role name</label>
    <input class="rounded border px-2 py-1" id="name" name="name" placeholder="new-role" required pattern="[a-z][a-z0-9_-]*">
    <button class="rounded border px-3 py-1" type="submit">New role</button>
</form>
@endcan

@if ($current)
    @php($locked = $current->name === \App\Http\Controllers\Admin\RoleController::SYSTEM_ROLE || ! auth()->user()->can('users.edit'))
    @if ($current->name === \App\Http\Controllers\Admin\RoleController::SYSTEM_ROLE)
        <p class="mb-3 rounded bg-blue-50 p-2 text-sm text-blue-800">Administrator is a system role and always has full access.</p>
    @endif
    <form method="POST" action="{{ route('admin.roles.update', $current) }}">
        @csrf @method('PUT')
        <table class="w-full border-collapse bg-white text-sm">
            <thead><tr class="border-b text-left"><th class="p-2">Module</th>@foreach ($actions as $a)<th class="p-2">{{ $a }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($matrix as $module => $perms)
                <tr class="border-b">
                    <th class="p-2 text-left font-medium" scope="row">{{ $module }}</th>
                    @foreach ($actions as $a)
                        <td class="p-2">@isset($perms[$a])<input type="checkbox" name="permissions[]" value="{{ $perms[$a] }}" aria-label="{{ $current->name }}: {{ $perms[$a] }}" @checked(in_array($perms[$a], $granted, true)) @disabled($locked)>@endisset</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        @unless ($locked)<button class="mt-4 rounded bg-slate-900 px-4 py-2 font-semibold text-white" type="submit">Save permissions</button>@endunless
    </form>
    @if ($current->name !== \App\Http\Controllers\Admin\RoleController::SYSTEM_ROLE)
        @can('users.delete')
        <form method="POST" action="{{ route('admin.roles.destroy', $current) }}" class="mt-4">
            @csrf @method('DELETE')
            <button class="rounded border border-red-700 px-3 py-1 text-sm text-red-700" type="submit">Delete role</button>
        </form>
        @endcan
    @endif
@endif
@endsection

@extends('admin.layout')
@section('title', 'Roles & permissions')
@section('content')
<div class="a-page-head"><div><h1>Roles &amp; permissions</h1></div></div>

<nav class="mb-4 flex flex-wrap gap-2" aria-label="Roles">
    @foreach ($roles as $r)
        <a href="{{ route('admin.roles.index', ['role' => $r->name]) }}" @class(['a-btn a-btn-sm', 'a-btn-primary' => $current?->is($r)]) @if ($current?->is($r)) aria-current="page" @endif>{{ $r->name }} <span class="opacity-70">({{ $r->users_count }})</span></a>
    @endforeach
</nav>

@can('users.create')
<form method="POST" action="{{ route('admin.roles.store') }}" class="mb-6 flex gap-2 text-sm">
    @csrf
    <label class="sr-only" for="name">Role name</label>
    <input class="a-input" id="name" name="name" placeholder="new-role" required pattern="[a-z][a-z0-9_-]*">
    <button class="a-btn a-btn-sm" type="submit">New role</button>
</form>
@endcan

@if ($current)
    @php($locked = $current->name === \App\Http\Controllers\Admin\RoleController::SYSTEM_ROLE || ! auth()->user()->can('users.edit'))
    @if ($current->name === \App\Http\Controllers\Admin\RoleController::SYSTEM_ROLE)
        <p class="a-badge info mb-3">Administrator is a system role and always has full access.</p>
    @endif
    <form method="POST" action="{{ route('admin.roles.update', $current) }}">
        @csrf @method('PUT')
        <div class="a-card"><div class="a-tablewrap"><table class="a-table">
            <thead><tr><th>Module</th>@foreach ($actions as $a)<th>{{ $a }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($matrix as $module => $perms)
                <tr>
                    <th class="text-left font-medium" scope="row">{{ $module }}</th>
                    @foreach ($actions as $a)
                        <td>@isset($perms[$a])<input type="checkbox" name="permissions[]" value="{{ $perms[$a] }}" aria-label="{{ $current->name }}: {{ $perms[$a] }}" @checked(in_array($perms[$a], $granted, true)) @disabled($locked)>@endisset</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
        @unless ($locked)<button class="a-btn a-btn-primary" type="submit">Save permissions</button>@endunless
    </form>
    @if ($current->name !== \App\Http\Controllers\Admin\RoleController::SYSTEM_ROLE)
        @can('users.delete')
        <form method="POST" action="{{ route('admin.roles.destroy', $current) }}" class="mt-4">
            @csrf @method('DELETE')
            <button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete role</button>
        </form>
        @endcan
    @endif
@endif
@endsection

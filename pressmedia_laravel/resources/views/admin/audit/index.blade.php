@extends('admin.layout')
@section('title', 'Audit log')
@section('content')
<h1 class="mb-1 text-2xl font-bold">Audit log</h1>
<p class="mb-4 text-sm text-slate-600">Append-only: entries cannot be edited or deleted.</p>
<form method="GET" class="mb-4 flex gap-2 text-sm">
    <input class="rounded border px-2 py-1" name="module" value="{{ $filters['module'] ?? '' }}" placeholder="Module" aria-label="Module">
    <input class="rounded border px-2 py-1" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Action" aria-label="Action">
    <button class="rounded border px-3 py-1" type="submit">Filter</button>
</form>
<table class="w-full border-collapse bg-white text-sm">
    <thead><tr class="border-b text-left">
        <th class="p-2">Date</th><th class="p-2">User</th><th class="p-2">Role</th><th class="p-2">Action</th><th class="p-2">Module</th><th class="p-2">Target</th><th class="p-2">Detail</th><th class="p-2">IP</th>
    </tr></thead>
    <tbody>
    @forelse ($entries as $e)
        <tr class="border-b">
            <td class="p-2 whitespace-nowrap">{{ $e->created_at?->format('Y-m-d H:i:s') }}</td>
            <td class="p-2">{{ $e->user_name }}</td><td class="p-2">{{ $e->role }}</td>
            <td class="p-2">{{ $e->action }}</td><td class="p-2">{{ $e->module }}</td>
            <td class="p-2">{{ $e->target }}</td><td class="p-2">{{ $e->detail }}</td><td class="p-2">{{ $e->ip }}</td>
        </tr>
    @empty
        <tr><td class="p-2" colspan="8">No entries.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="mt-4">{{ $entries->links() }}</div>
@endsection

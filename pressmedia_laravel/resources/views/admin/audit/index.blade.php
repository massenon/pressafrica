@extends('admin.layout')
@section('title', 'Audit log')
@section('content')
<div class="a-page-head"><div><h1>Audit log</h1></div></div>
<p class="mb-4 text-sm a-muted">Append-only: entries cannot be edited or deleted.</p>
<form method="GET" class="mb-4 flex gap-2 text-sm">
    <input class="a-input" name="module" value="{{ $filters['module'] ?? '' }}" placeholder="Module" aria-label="Module">
    <input class="a-input" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Action" aria-label="Action">
    <button class="a-btn a-btn-sm" type="submit">Filter</button>
</form>
<div class="a-card"><div class="a-tablewrap"><table class="a-table">
    <thead><tr>
        <th>Date</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Target</th><th>Detail</th><th>IP</th>
    </tr></thead>
    <tbody>
    @forelse ($entries as $e)
        <tr>
            <td class="whitespace-nowrap">{{ $e->created_at?->format('Y-m-d H:i:s') }}</td>
            <td>{{ $e->user_name }}</td><td>{{ $e->role }}</td>
            <td>{{ $e->action }}</td><td>{{ $e->module }}</td>
            <td>{{ $e->target }}</td><td>{{ $e->detail }}</td><td>{{ $e->ip }}</td>
        </tr>
    @empty
        <tr><td colspan="8">No entries.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-4">{{ $entries->links() }}</div>
@endsection

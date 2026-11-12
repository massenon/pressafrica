@extends('admin.layout')
@section('title', 'Backups')
@section('page', 'backups')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">System</p><h1>Backups</h1><p>Database every night at 03:15 and uploaded media every Sunday (Benin time), encrypted with the application key; the last {{ \App\Support\Settings::get('backupKeep') }} database backups are kept.</p></div>
@can('backups.create')<form method="POST" action="{{ route('admin.backups.store') }}" class="flex gap-2 items-center">@csrf<label class="a-check"><input type="checkbox" name="media" value="1"><span>with media</span></label><button class="a-btn a-btn-primary" type="submit">Back up now</button></form>@endcan</div>
<div class="a-card a-card-b mb-4"><p class="a-hint">Keep <code>APP_KEY</code> (in the server's .env) somewhere safe: a backup cannot be read without it. Restoring replaces the whole database; a safety copy of the current state is taken first.</p></div>
<div class="a-card"><div class="a-tablewrap"><table class="a-table" aria-label="Backups"><thead><tr><th scope="col">File</th><th scope="col">Date</th><th scope="col" class="text-right">Size</th><th scope="col"><span class="a-vh">Actions</span></th></tr></thead><tbody>
@forelse ($backups as $b)
<tr><td class="a-mono text-sm">{{ $b['name'] }}</td><td class="text-sm">{{ \Illuminate\Support\Carbon::createFromTimestamp($b['at'])->timezone(config('app.timezone'))->format('j M Y H:i') }}</td><td class="text-right">{{ number_format($b['size'] / 1024, 0, ',', ' ') }} kB</td>
<td class="text-right whitespace-nowrap"><a class="a-btn a-btn-sm" href="{{ route('admin.backups.download', $b['name']) }}">Download</a>
@if (str_ends_with($b['name'], '.sql.gz.enc'))@can('backups.publish')<details style="display:inline-block"><summary class="a-btn a-btn-sm">Restore…</summary><form method="POST" action="{{ route('admin.backups.restore', $b['name']) }}" class="a-card a-card-b" style="position:absolute;right:1rem;z-index:5">@csrf<label class="a-label" for="c-{{ $loop->index }}">Type RESTORE to replace the database</label><input class="a-input" id="c-{{ $loop->index }}" name="confirm" autocomplete="off" required><button class="a-btn a-btn-danger mt-2" type="submit">Restore</button></form></details>@endcan @endif
@can('backups.delete')<form method="POST" action="{{ route('admin.backups.destroy', $b['name']) }}" class="inline" data-confirm="Delete this backup?">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan</td></tr>
@empty
<tr><td colspan="4">No backup yet.</td></tr>
@endforelse
</tbody></table></div></div>
@endsection

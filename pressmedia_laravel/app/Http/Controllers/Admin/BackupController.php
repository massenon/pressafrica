<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Backup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Admin > Backups (administrators only by default, like the prototype): list, run, download, restore, delete. */
class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backups.index', ['backups' => Backup::list()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = Backup::run('manual');
        if ($request->boolean('media')) {
            Backup::media();
        }
        AuditLog::record('backup', 'backups', basename($name), $request->boolean('media') ? 'with media' : null);

        return back()->with('status', 'Backup created: '.basename($name));
    }

    public function download(string $file): StreamedResponse
    {
        $path = Backup::DIR.'/'.basename($file);
        abort_unless(Storage::disk('local')->exists($path), 404);
        AuditLog::record('download', 'backups', basename($file));

        return Storage::disk('local')->download($path);
    }

    public function restore(Request $request, string $file): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:RESTORE']]);
        abort_unless(str_ends_with($file, '.sql.gz.enc') && Storage::disk('local')->exists(Backup::DIR.'/'.basename($file)), 404);
        try {
            $safety = Backup::run('before-restore');
            Backup::restore($file);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['confirm' => 'Restore failed: '.$e->getMessage()]);
        }
        AuditLog::record('restore', 'backups', basename($file), 'safety copy '.basename($safety));

        return back()->with('status', 'Database restored from '.basename($file).'. Safety copy: '.basename($safety));
    }

    public function destroy(string $file): RedirectResponse
    {
        Storage::disk('local')->delete(Backup::DIR.'/'.basename($file));
        AuditLog::record('delete', 'backups', basename($file));

        return back()->with('status', 'Backup deleted.');
    }
}

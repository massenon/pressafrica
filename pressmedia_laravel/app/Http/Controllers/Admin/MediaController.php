<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Post;
use App\Support\MediaStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    public function index(Request $request): View
    {
        $q = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;
        $media = Media::query()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('alt_fr', 'like', "%$q%")->orWhere('alt_en', 'like', "%$q%")))
            ->latest('id')->paginate(40)->withQueryString();

        return view('admin.media.index', compact('media', 'q'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            // Type read from the file's bytes (finfo), never from its name or the browser: a renamed script is rejected.
            'files.*' => ['file', 'max:10240', function ($attr, $file, $fail) {
                if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()), self::ALLOWED, true)) {
                    $fail('Only JPEG, PNG, GIF, WebP images and PDF files are accepted.');
                }
            }],
            'alt_fr' => ['nullable', 'string', 'max:255'],
            'alt_en' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($request->file('files') as $file) {
            $media = Media::create(MediaStore::store($file) + [
                'alt_fr' => $request->input('alt_fr'), 'alt_en' => $request->input('alt_en'), 'uploaded_by' => $request->user()->id,
            ]);
            AuditLog::record('create', 'media', 'media:'.$media->id, $media->mime);
        }

        return back()->with('status', count($request->file('files')).' file(s) uploaded.');
    }

    /** Alt text is required for SEO and a11y on public pages (EX-35); it is edited here, per language. */
    public function update(Request $request, Media $medium): RedirectResponse
    {
        $data = $request->validate(['alt_fr' => ['nullable', 'string', 'max:255'], 'alt_en' => ['nullable', 'string', 'max:255']]);
        $medium->update($data);
        AuditLog::record('update', 'media', 'media:'.$medium->id, 'alt text');

        return back()->with('status', 'Saved.');
    }

    public function destroy(Media $medium): RedirectResponse
    {
        if (Post::withTrashed()->where('cover_id', $medium->id)->exists()) {
            return back()->withErrors(['media' => 'This file is a post cover; replace it on the post first.']);
        }
        MediaStore::delete($medium->path);
        $medium->delete();
        AuditLog::record('delete', 'media', 'media:'.$medium->id, $medium->path);

        return back()->with('status', 'File deleted.');
    }
}

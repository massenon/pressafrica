<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MediaItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * One admin module for the media sections (EX-03, EX-06, modelled on the prototype's admin-podcasts.js):
 * list, create, edit, draft/published/archived, FR and EN. Fields per type come from MediaItem::TYPES.
 */
class MediaItemController extends Controller
{
    public const STATUSES = ['draft', 'published', 'archived'];

    /** Accepted uploads, checked on the file bytes (finfo), not on the name. */
    private const UPLOADS = ['file' => ['video/mp4', 'video/webm', 'audio/mpeg'], 'poster' => ['image/webp', 'image/jpeg', 'image/png']];

    public function index(Request $request, string $type): View
    {
        $this->can($request, $type, 'view');
        $status = $request->validate(['status' => ['nullable', Rule::in(self::STATUSES)]])['status'] ?? null;
        $items = MediaItem::ofType($type)->with('translations')->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('published_on')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('admin.media-items.index', compact('items', 'type', 'status'));
    }

    public function create(Request $request, string $type): View
    {
        $this->can($request, $type, 'create');

        return $this->form($type, new MediaItem(['type' => $type, 'status' => 'draft', 'data' => []]));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->can($request, $type, 'create');
        $item = new MediaItem(['type' => $type]);
        $this->save($request, $item);
        AuditLog::record('create', MediaItem::TYPES[$type]['module'], "$type:{$item->key}");

        return redirect()->route('admin.media-items.edit', [$type, $item])->with('status', 'Saved.');
    }

    public function edit(Request $request, string $type, MediaItem $item): View
    {
        $this->can($request, $type, 'edit', $item);

        return $this->form($type, $item->load('translations', 'posts'));
    }

    public function update(Request $request, string $type, MediaItem $item): RedirectResponse
    {
        $this->can($request, $type, 'edit', $item);
        $before = $item->status;
        $this->save($request, $item);
        AuditLog::record('update', MediaItem::TYPES[$type]['module'], "$type:{$item->key}", $before !== $item->status ? "status: $before → {$item->status}" : null);

        return redirect()->route('admin.media-items.edit', [$type, $item])->with('status', 'Saved.');
    }

    public function destroy(Request $request, string $type, MediaItem $item): RedirectResponse
    {
        $this->can($request, $type, 'delete', $item);
        $item->delete();
        AuditLog::record('delete', MediaItem::TYPES[$type]['module'], "$type:{$item->key}");

        return redirect()->route('admin.media-items.index', $type)->with('status', 'Deleted.');
    }

    private function can(Request $request, string $type, string $action, ?MediaItem $item = null): void
    {
        abort_unless(isset(MediaItem::TYPES[$type]) && (! $item || $item->type === $type), 404);
        abort_unless($request->user()->can(MediaItem::TYPES[$type]['module'].".$action"), 403);
    }

    private function form(string $type, MediaItem $item): View
    {
        return view('admin.media-items.form', [
            'type' => $type, 'item' => $item, 'def' => MediaItem::TYPES[$type],
            'statuses' => auth()->user()->can(MediaItem::TYPES[$type]['module'].'.publish') ? self::STATUSES : ['draft', 'archived'],
            'shows' => MediaItem::ofType('podcast_show')->with('translations')->get(),
        ]);
    }

    private function save(Request $request, MediaItem $item): void
    {
        $type = $item->type;
        $def = MediaItem::TYPES[$type];
        $canPublish = $request->user()->can($def['module'].'.publish');
        $rules = [
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('media_items', 'key')->ignore($item->id)],
            // Publishing needs the publish right; keeping an already published item published does not.
            'status' => ['required', Rule::in($canPublish || $item->status === 'published' ? self::STATUSES : ['draft', 'archived'])],
            'published_on' => ['nullable', 'date'],
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'articles' => ['nullable', 'array', 'max:30'],
            'articles.*' => ['integer', 'exists:posts,id'],
        ];
        foreach ($def['data'] as $field => $input) {
            $rules["data.$field"] = match ($input) {
                'number' => ['nullable', 'integer', 'min:0'],
                'url' => ['nullable', 'url:https,http', 'max:255'],
                'show' => ['nullable', Rule::exists('media_items', 'key')->where('type', 'podcast_show')],
                'file' => ['nullable', 'string', 'max:255'],
                default => ['nullable', 'string', 'max:255'],
            };
            if ($input === 'file') {
                $rules["upload.$field"] = ['nullable', 'file', 'max:51200', $this->sniff(self::UPLOADS[$field] ?? [])];
            }
        }
        foreach (['fr', 'en'] as $l) {
            $rules["translations.$l.title"] = [in_array('title', $def['translated']) && $l === 'fr' ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["translations.$l.description"] = ['nullable', 'string', 'max:2000'];
            $rules["translations.$l.file"] = ['nullable', 'string', 'max:255'];
            $rules["upload_$l"] = ['nullable', 'file', 'max:51200', $this->sniff(['audio/mpeg'])];
        }
        $data = $request->validate($rules);

        DB::transaction(function () use ($request, $item, $data, $def, $type) {
            $values = $item->data ?? [];
            foreach ($def['data'] as $field => $input) {
                $values[$field] = $input === 'number' && isset($data['data'][$field]) ? (int) $data['data'][$field] : ($data['data'][$field] ?? null);
                if ($request->hasFile("upload.$field")) {
                    $values[$field] = $this->storeUpload($request->file("upload.$field"));
                }
            }
            $item->fill(['key' => $data['key'], 'status' => $data['status'], 'published_on' => $data['published_on'] ?? null,
                'post_id' => $data['post_id'] ?? null, 'data' => $values])->save();
            foreach (['fr', 'en'] as $l) {
                $tr = $data['translations'][$l] ?? [];
                if ($request->hasFile("upload_$l")) {
                    $tr['file'] = $this->storeUpload($request->file("upload_$l"));
                }
                $item->translations()->updateOrCreate(['locale' => $l], collect($tr)->only($def['translated'])->all());
            }
            if ($type === 'magazine') {
                // Contents in the order chosen in the picker.
                $ids = collect($data['articles'] ?? [])->map(fn ($i) => (int) $i)->unique()->values();
                $item->posts()->sync($ids->mapWithKeys(fn ($id, $n) => [$id => ['position' => $n + 1]])->all());
            }
        });
    }

    private function sniff(array $mimes): \Closure
    {
        return function ($attr, $file, $fail) use ($mimes) {
            if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()), $mimes, true)) {
                $fail('File type not accepted: '.implode(', ', $mimes).'.');
            }
        };
    }

    private function storeUpload($file): string
    {
        return $file->storeAs('media/'.now()->format('Y/m'), Str::random(32).'.'.$file->guessExtension(), 'public');
    }
}

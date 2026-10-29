<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HeroSlide;
use App\Models\Media;
use App\Models\Post;
use App\Support\MediaStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Admin > Featured: the home carousel (spec EX-41). Every change is journaled under the "featured" module. */
class HeroSlideController extends Controller
{
    private const TEXT = ['title', 'text', 'cta', 'url', 'alt'];

    public function index(): View
    {
        $slides = HeroSlide::where('placement', 'home')->with(['translations', 'media', 'post.translations', 'post.cover'])->orderBy('position')->orderBy('id')->get();
        $live = HeroSlide::live()->pluck('id');

        return view('admin.featured.index', compact('slides', 'live'));
    }

    public function create(): View
    {
        return $this->form(new HeroSlide(['status' => 'draft']));
    }

    public function store(Request $request): RedirectResponse
    {
        $slide = new HeroSlide(['placement' => 'home', 'position' => (int) HeroSlide::where('placement', 'home')->max('position') + 1]);
        $this->save($request, $slide);
        AuditLog::record('create', 'featured', 'slide:'.$slide->id, $this->label($slide));

        return redirect()->route('admin.featured.index')->with('status', 'Slide created.');
    }

    public function edit(HeroSlide $slide): View
    {
        return $this->form($slide->load('translations'));
    }

    public function update(Request $request, HeroSlide $slide): RedirectResponse
    {
        $changed = $this->save($request, $slide);
        AuditLog::record('update', 'featured', 'slide:'.$slide->id, $changed ? 'fields: '.implode(', ', $changed) : null);

        return redirect()->route('admin.featured.index')->with('status', 'Slide saved.');
    }

    /** Moves a slide one place up or down (keyboard-friendly alternative to drag and drop). */
    public function move(Request $request, HeroSlide $slide): RedirectResponse
    {
        $dir = $request->validate(['dir' => ['required', Rule::in(['up', 'down'])]])['dir'];
        DB::transaction(function () use ($slide, $dir) {
            $list = HeroSlide::where('placement', 'home')->orderBy('position')->orderBy('id')->get()->values();
            $i = $list->search(fn ($s) => $s->id === $slide->id);
            $j = $dir === 'up' ? $i - 1 : $i + 1;
            if ($j < 0 || $j >= $list->count()) {
                return;
            }
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
            $list->each(fn ($s, $n) => $s->update(['position' => $n + 1]));
        });
        AuditLog::record('reorder', 'featured', 'slide:'.$slide->id, "moved $dir");

        return back();
    }

    public function destroy(HeroSlide $slide): RedirectResponse
    {
        $slide->delete();
        AuditLog::record('delete', 'featured', 'slide:'.$slide->id, $this->label($slide));

        return redirect()->route('admin.featured.index')->with('status', 'Slide deleted.');
    }

    public function restore(): RedirectResponse
    {
        $slides = HeroSlide::restoreDefaults();
        AuditLog::record('restore', 'featured', 'carousel', 'default carousel restored ('.$slides->count().' slides)');

        return redirect()->route('admin.featured.index')->with('status', 'Default carousel restored.');
    }

    private function form(HeroSlide $slide): View
    {
        return view('admin.featured.form', [
            'slide' => $slide,
            'posts' => Post::published()->with('translations')->orderByDesc('published_at')->get(),
            'images' => Media::where('mime', 'like', 'image/%')->latest('id')->get(),
            'canPublish' => auth()->user()->can('featured.publish'),
        ]);
    }

    /** @return list<string> changed field names, for the audit log */
    private function save(Request $request, HeroSlide $slide): array
    {
        $canPublish = $request->user()->can('featured.publish');
        $data = $request->validate([
            'kind' => ['required', Rule::in(['article', 'free'])],
            'post_id' => ['nullable', 'required_if:kind,article', 'integer', Rule::exists('posts', 'id')->where('status', 'published')],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'upload' => ['nullable', 'file', 'max:10240', function ($a, $f, $fail) {
                if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($f->getRealPath()), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $fail('JPEG, PNG or WebP images only.');
                }
            }],
            'status' => ['required', Rule::in($canPublish || $slide->status === 'published' ? ['draft', 'published'] : ['draft'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'translations.fr.title' => ['nullable', 'required_if:kind,free', 'string', 'max:200'],
            'translations.fr.alt' => ['nullable', 'string', 'max:255'],
            'translations.*.title' => ['nullable', 'string', 'max:200'],
            'translations.*.text' => ['nullable', 'string', 'max:500'],
            'translations.*.cta' => ['nullable', 'string', 'max:60'],
            'translations.*.url' => ['nullable', 'string', 'max:255', 'regex:#^(/|https?://)#'],
            'translations.*.alt' => ['nullable', 'string', 'max:255'],
        ]);
        $free = $data['kind'] === 'free';
        if ($free && empty($data['media_id']) && ! $request->hasFile('upload') && ! $slide->media_id) {
            throw ValidationException::withMessages(['media_id' => 'A free slide needs an image.']);
        }
        // Alt text is required for every image the slide itself carries (EX-35); article slides reuse the cover's.
        if (($free || ! empty($data['media_id']) || $request->hasFile('upload')) && blank($data['translations']['fr']['alt'] ?? null)) {
            throw ValidationException::withMessages(['translations.fr.alt' => 'The French alternative text of the image is required.']);
        }
        if ($data['status'] === 'published' && HeroSlide::where('placement', 'home')->where('status', 'published')->whereKeyNot($slide->id)->count() >= HeroSlide::MAX_LIVE) {
            throw ValidationException::withMessages(['status' => 'At most '.HeroSlide::MAX_LIVE.' published slides: hide one first.']);
        }

        return DB::transaction(function () use ($request, $slide, $data, $free) {
            $mediaId = $data['media_id'] ?? ($free ? $slide->media_id : null);
            if ($request->hasFile('upload')) {
                $mediaId = Media::create(MediaStore::store($request->file('upload')) + ['alt_fr' => $data['translations']['fr']['alt'] ?? null,
                    'alt_en' => $data['translations']['en']['alt'] ?? null, 'uploaded_by' => $request->user()->id])->id;
            }
            $slide->fill(['post_id' => $free ? null : $data['post_id'], 'media_id' => $mediaId, 'status' => $data['status'],
                'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null]);
            $changed = array_keys($slide->getDirty());
            $slide->save();
            foreach (['fr', 'en'] as $l) {
                $values = collect($data['translations'][$l] ?? [])->only(self::TEXT)->map(fn ($v) => blank($v) ? null : $v)->all();
                $tr = $slide->translations()->firstOrNew(['locale' => $l]);
                $tr->fill($values + array_fill_keys(self::TEXT, null));
                if ($tr->isDirty()) {
                    $changed[] = "$l texts";
                }
                $tr->save();
            }

            return array_values(array_diff($changed, ['updated_at', 'created_at']));
        });
    }

    private function label(HeroSlide $slide): string
    {
        return (string) ($slide->translations()->where('locale', 'fr')->value('title') ?: $slide->post?->translation('fr')?->title);
    }
}

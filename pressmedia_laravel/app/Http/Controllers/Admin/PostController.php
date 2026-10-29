<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Posts (EX-24, EX-25). French is required, English optional (INV-10: a post may be published in French only).
 * Every body is sanitised with Purifier before it is stored (INV-04).
 */
class PostController extends Controller
{
    use AuthorizesRequests;

    private const LOCALES = ['fr', 'en'];

    private const TEXT_FIELDS = ['title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Post::class);
        $filters = $request->validate(['status' => ['nullable', Rule::in(Post::STATUSES)], 'q' => ['nullable', 'string', 'max:100']]);

        $posts = Post::with(['translations', 'author'])
            ->unless($request->user()->can('posts.publish'), fn ($q) => $q->where('author_id', $request->user()->id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('translations', fn ($t) => $t->where('title', 'like', '%'.$term.'%')))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.posts.index', compact('posts', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', Post::class);

        return $this->form(new Post(['status' => 'draft']));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Post::class);
        $post = new Post(['author_id' => $request->user()->id]);
        $this->save($request, $post);
        AuditLog::record('create', 'posts', 'post:'.$post->id, $post->translation('fr')->title);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post created.');
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        return $this->form($post->load('translations', 'categories', 'tags'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);
        $before = $post->status;
        $this->save($request, $post);
        $detail = $before !== $post->status ? "status: $before → {$post->status}" : null;
        AuditLog::record($post->status === 'published' && $before !== 'published' ? 'publish' : 'update', 'posts', 'post:'.$post->id, $detail);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post saved.');
    }

    /** Soft delete: posts are archived, not erased (PRD INV-01). */
    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);
        $post->delete();
        AuditLog::record('delete', 'posts', 'post:'.$post->id, $post->translation('fr')?->title);

        return redirect()->route('admin.posts.index')->with('status', 'Post moved to trash.');
    }

    private function form(Post $post): View
    {
        return view('admin.posts.form', [
            'post' => $post,
            'categories' => Category::with('translations')->orderBy('position')->get(),
            'statuses' => auth()->user()->can('posts.publish') ? Post::STATUSES : ['draft', 'pending'],
        ]);
    }

    private function save(Request $request, Post $post): void
    {
        $canPublish = $request->user()->can('posts.publish');
        $data = $request->validate($this->rules($post, $canPublish));
        $at = isset($data['published_at']) ? Carbon::parse($data['published_at']) : null;

        // Published with a future date is a schedule; scheduled without a date is invalid (caught by rules).
        $status = $data['status'];
        if ($status === 'published' && $at?->isFuture()) {
            $status = 'scheduled';
        }
        if ($status === 'published') {
            $at ??= $post->published_at ?? now();
        }

        DB::transaction(function () use ($post, $data, $status, $at) {
            $post->fill(['status' => $status, 'published_at' => $at, 'featured' => (bool) ($data['featured'] ?? false)])->save();

            foreach (self::LOCALES as $locale) {
                $fields = $data['translations'][$locale] ?? [];
                if (blank($fields['title'] ?? null)) {
                    $post->translations()->where('locale', $locale)->delete();   // only reachable for 'en'

                    continue;
                }
                $fields['slug'] = ($fields['slug'] ?? null) ?: $this->uniqueSlug($locale, Str::slug($fields['title']), $post->id);
                $fields['body'] = clean($fields['body'] ?? '');
                $post->translations()->updateOrCreate(['locale' => $locale], collect($fields)->only(self::TEXT_FIELDS)->all());
            }

            $post->categories()->sync($data['categories'] ?? []);
            $post->tags()->sync(Tag::idsFromNames(explode(',', $data['tags'] ?? '')));
        });
        $post->unsetRelation('translations');
    }

    /** An explicit slug is validated as unique; one derived from the title gets a -2, -3... suffix instead. */
    private function uniqueSlug(string $locale, string $base, ?int $postId): string
    {
        $base = $base ?: 'post';
        $taken = fn ($slug) => PostTranslation::where('locale', $locale)->where('slug', $slug)->where('post_id', '!=', $postId ?? 0)->exists();
        for ($slug = $base, $i = 2; $taken($slug); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    private function rules(Post $post, bool $canPublish): array
    {
        $rules = [
            'status' => ['required', Rule::in($canPublish ? Post::STATUSES : ['draft', 'pending'])],
            'published_at' => ['nullable', 'date', 'required_if:status,scheduled', Rule::when(request('status') === 'scheduled', 'after:now')],
            'featured' => ['boolean'],
            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string', 'max:500'],
        ];
        foreach (self::LOCALES as $locale) {
            $p = "translations.$locale";
            $required = $locale === 'fr' ? 'required' : "required_with:$p.title";
            $rules += [
                "$p.title" => [$locale === 'fr' ? 'required' : 'nullable', 'string', 'max:255'],
                "$p.slug" => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    Rule::unique(PostTranslation::class, 'slug')->where('locale', $locale)->ignore($post->id, 'post_id')],
                "$p.excerpt" => ['nullable', 'string', 'max:1000'],
                "$p.body" => [$required, 'nullable', 'string'],
                "$p.seo_title" => ['nullable', 'string', 'max:255'],
                "$p.seo_description" => ['nullable', 'string', 'max:320'],
            ];
        }

        return $rules;
    }
}

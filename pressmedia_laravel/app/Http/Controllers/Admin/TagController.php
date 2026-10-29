<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tags (EX-23): create, rename, merge, delete, with usage counts. */
class TagController extends Controller
{
    public function index(): View
    {
        return view('admin.tags.index', ['tags' => Tag::withCount('posts')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:80']])['name'];
        $slug = Str::slug($name);
        $request->merge(['slug' => $slug])->validate(['slug' => ['required', Rule::unique('tags', 'slug')]]);
        $tag = Tag::create(['name' => $name, 'slug' => $slug]);
        AuditLog::record('create', 'tags', 'tag:'.$tag->slug);

        return back()->with('status', 'Tag created.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:80']])['name'];
        $request->merge(['slug' => Str::slug($name)])->validate(['slug' => ['required', Rule::unique('tags', 'slug')->ignore($tag->id)]]);
        $old = $tag->name;
        $tag->update(['name' => $name, 'slug' => Str::slug($name)]);
        AuditLog::record('update', 'tags', 'tag:'.$tag->slug, "renamed from $old");

        return back()->with('status', 'Tag renamed.');
    }

    /** Moves every post of $tag onto the target tag, then removes $tag. */
    public function merge(Request $request, Tag $tag): RedirectResponse
    {
        $target = Tag::findOrFail($request->validate(['into' => ['required', 'integer', 'exists:tags,id', Rule::notIn([$tag->id])]])['into']);
        DB::transaction(function () use ($tag, $target) {
            $target->posts()->syncWithoutDetaching($tag->posts()->pluck('posts.id'));
            $tag->delete();
        });
        AuditLog::record('merge', 'tags', 'tag:'.$tag->slug, 'into '.$target->slug);

        return back()->with('status', 'Tags merged.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();
        AuditLog::record('delete', 'tags', 'tag:'.$tag->slug);

        return back()->with('status', 'Tag deleted.');
    }
}

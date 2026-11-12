<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Categories with unlimited nesting (parent > child > grandchild...). French name required, English optional. */
class CategoryController extends Controller
{
    public function index(): View
    {
        $all = Category::with('translations')->withCount('posts')->orderBy('position')->get();

        return view('admin.categories.index', ['tree' => $this->flatten($all), 'all' => $all, 'category' => new Category]);
    }

    public function edit(Category $category): View
    {
        $all = Category::with('translations')->orderBy('position')->get();

        return view('admin.categories.index', ['tree' => $this->flatten($all->loadCount('posts')), 'all' => $all, 'category' => $category->load('translations')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = new Category;
        $this->save($request, $category);
        AuditLog::record('create', 'categories', 'category:'.$category->key);

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->save($request, $category);
        AuditLog::record('update', 'categories', 'category:'.$category->key);

        return redirect()->route('admin.categories.index')->with('status', 'Category saved.');
    }

    /** Refused while the category still has posts or children, so nothing is orphaned silently. */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->posts()->exists() || Category::where('parent_id', $category->id)->exists()) {
            return back()->withErrors(['category' => 'Move its posts and sub-categories first.']);
        }
        $category->delete();
        AuditLog::record('delete', 'categories', 'category:'.$category->key);

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    private function save(Request $request, Category $category): void
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'key')->ignore($category->id)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'translations.fr.name' => ['required', 'string', 'max:120'],
            'translations.en.name' => ['nullable', 'string', 'max:120'],
            'translations.*.slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
            'translations.*.seo_title' => ['nullable', 'string', 'max:255'],
        ]);
        if ($category->exists && $this->wouldCycle($category, $data['parent_id'] ?? null)) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be placed under itself or one of its descendants.']);
        }

        DB::transaction(function () use ($category, $data) {
            $category->fill(['key' => $data['key'], 'parent_id' => $data['parent_id'] ?? null, 'position' => $data['position'] ?? 0])->save();
            foreach (['fr', 'en'] as $locale) {
                $t = $data['translations'][$locale] ?? [];
                if (blank($t['name'] ?? null)) {
                    $category->translations()->where('locale', $locale)->delete();

                    continue;
                }
                $t['slug'] = ($t['slug'] ?? null) ?: Str::slug($t['name']);
                if (CategoryTranslation::where('locale', $locale)->where('slug', $t['slug'])->where('category_id', '!=', $category->id)->exists()) {
                    throw ValidationException::withMessages(["translations.$locale.slug" => "The $locale slug is already used."]);
                }
                $category->translations()->updateOrCreate(['locale' => $locale], collect($t)->only(['name', 'slug', 'description', 'seo_title'])->all());
            }
        });
    }

    private function wouldCycle(Category $category, ?int $parentId): bool
    {
        for ($id = $parentId; $id; $id = Category::whereKey($id)->value('parent_id')) {
            if ($id === $category->id) {
                return true;
            }
        }

        return false;
    }

    /** Depth-first list of [category, depth] for display. */
    private function flatten($all, ?int $parent = null, int $depth = 0): array
    {
        $rows = [];
        foreach ($all->where('parent_id', $parent) as $c) {
            $rows[] = [$c, $depth];
            array_push($rows, ...$this->flatten($all, $c->id, $depth + 1));
        }

        return $rows;
    }
}

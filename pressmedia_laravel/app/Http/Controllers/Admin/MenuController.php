<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin > Menus: the public main menu and its mega panels. Every change is journaled ("menus"). */
class MenuController extends Controller
{
    public function index(): View
    {
        return view('admin.menus.index', ['items' => MenuItem::where('location', 'main')->with(['translations', 'category.translations'])->orderBy('position')->get()]);
    }

    public function create(): View
    {
        return $this->form(new MenuItem(['panel' => 'none', 'active' => true, 'data' => []]));
    }

    public function store(Request $request): RedirectResponse
    {
        $item = new MenuItem(['location' => 'main', 'position' => (int) MenuItem::max('position') + 1]);
        $this->save($request, $item);
        AuditLog::record('create', 'menus', 'menu:'.$item->key);

        return redirect()->route('admin.menus.index')->with('status', 'Menu entry created.');
    }

    public function edit(MenuItem $menu): View
    {
        return $this->form($menu->load('translations'));
    }

    public function update(Request $request, MenuItem $menu): RedirectResponse
    {
        $this->save($request, $menu);
        AuditLog::record('update', 'menus', 'menu:'.$menu->key);

        return redirect()->route('admin.menus.index')->with('status', 'Menu entry saved.');
    }

    public function move(Request $request, MenuItem $menu): RedirectResponse
    {
        $dir = $request->validate(['dir' => ['required', Rule::in(['up', 'down'])]])['dir'];
        DB::transaction(function () use ($menu, $dir) {
            $list = MenuItem::where('location', 'main')->orderBy('position')->get()->values();
            $i = $list->search(fn ($m) => $m->id === $menu->id);
            $j = $dir === 'up' ? $i - 1 : $i + 1;
            if ($j >= 0 && $j < $list->count()) {
                [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
                $list->each(fn ($m, $n) => $m->update(['position' => $n + 1]));
            }
        });
        AuditLog::record('reorder', 'menus', 'menu:'.$menu->key, "moved $dir");

        return back();
    }

    public function destroy(MenuItem $menu): RedirectResponse
    {
        $menu->delete();
        AuditLog::record('delete', 'menus', 'menu:'.$menu->key);

        return redirect()->route('admin.menus.index')->with('status', 'Menu entry deleted.');
    }

    public function restore(): RedirectResponse
    {
        MenuItem::restoreDefaults();
        AuditLog::record('restore', 'menus', 'main', 'default menu restored');

        return redirect()->route('admin.menus.index')->with('status', 'Default menu restored.');
    }

    private function form(MenuItem $item): View
    {
        return view('admin.menus.form', ['item' => $item, 'categories' => Category::with('translations')->orderBy('position')->get()]);
    }

    private function save(Request $request, MenuItem $item): void
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9-]+$/', Rule::unique('menu_items', 'key')->ignore($item->id)],
            'panel' => ['required', Rule::in(MenuItem::PANELS)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'from_category' => ['nullable', 'boolean'],
            'picks' => ['nullable', 'array', 'max:3'],
            'picks.*' => ['integer', 'exists:posts,id'],
            'active' => ['nullable', 'boolean'],
            'translations.fr.label' => ['required', 'string', 'max:80'],
            'translations.en.label' => ['nullable', 'string', 'max:80'],
            'translations.*.url' => ['nullable', 'string', 'max:255', 'regex:#^(/|https?://)#'],
        ]);
        DB::transaction(function () use ($item, $data, $request) {
            $item->fill(['key' => $data['key'], 'panel' => $data['panel'], 'category_id' => $data['category_id'] ?? null, 'active' => $request->boolean('active'),
                'data' => array_filter(['from_category' => $request->boolean('from_category') ?: null, 'picks' => array_values(array_map('intval', $data['picks'] ?? [])) ?: null])])->save();
            foreach (['fr', 'en'] as $l) {
                $tr = $data['translations'][$l] ?? [];
                if (blank($tr['label'] ?? null)) {
                    $item->translations()->where('locale', $l)->delete();

                    continue;
                }
                $item->translations()->updateOrCreate(['locale' => $l], ['label' => $tr['label'], 'url' => ($tr['url'] ?? null) ?: null]);
            }
        });
    }
}

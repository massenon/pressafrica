<?php

namespace App\Livewire;

use App\Models\Post;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Article selection by title instead of typed ids (spec EX-48). Single mode fills `name`; multiple mode fills
 * `name[]` in the chosen order (magazine contents).
 */
class PostPicker extends Component
{
    #[Locked]
    public string $name = 'post_id';

    #[Locked]
    public bool $multiple = false;

    /** @var list<int> */
    public array $selected = [];

    public string $q = '';

    public function mount($value = null): void
    {
        $this->selected = array_values(array_filter(array_map('intval', (array) $value)));
    }

    public function add(int $id): void
    {
        if (! Post::whereKey($id)->exists()) {
            return;
        }
        $this->selected = $this->multiple ? array_values(array_unique([...$this->selected, $id])) : [$id];
        $this->q = '';
    }

    public function remove(int $id): void
    {
        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    public function move(int $id, int $by): void
    {
        $i = array_search($id, $this->selected, true);
        $j = $i + $by;
        if ($i === false || $j < 0 || $j >= count($this->selected)) {
            return;
        }
        [$this->selected[$i], $this->selected[$j]] = [$this->selected[$j], $this->selected[$i]];
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('posts.view') || auth()->user()?->can('featured.view'), 403);
        $chosen = Post::with('translations')->whereIn('id', $this->selected)->get()->keyBy('id');

        return view('livewire.post-picker', [
            'chosen' => collect($this->selected)->map(fn ($id) => $chosen[$id] ?? null)->filter(),
            'results' => mb_strlen(trim($this->q)) < 2 ? collect() : Post::with('translations')
                ->whereHas('translations', fn ($t) => $t->where('title', 'like', '%'.trim($this->q).'%'))
                ->whereNotIn('id', $this->selected)->orderByDesc('published_at')->take(8)->get(),
        ]);
    }
}

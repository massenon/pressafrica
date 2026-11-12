<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Support\CommentGuard;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Public comment and reply form of the article page (prototype fields + spec EX-45, S4, S5). */
class CommentController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $post = Post::published()->whereHas('translations', fn ($t) => $t->where('locale', app()->getLocale())->where('slug', $slug))->firstOrFail();
        $moderated = (bool) Settings::get('commentsApproval');

        if (filled($request->input('website'))) {
            return $this->done($request, $moderated);
        }
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
            'author' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:100'],
            'consent' => ['accepted'],
            'notify' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        // Replies: one level only, to a published comment of the same article and language.
        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = Comment::approved()->whereNull('parent_id')->where('post_id', $post->id)->where('locale', app()->getLocale())->find($data['parent_id']);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => __('site.cm_reply_invalid')]);
            }
        }

        $body = strip_tags($data['comment']);   // plain text only: no markup from readers
        $verdict = CommentGuard::verdict($request->input('ft'), $request->input('website'), $post->id, $data['email'], $body);
        if ($verdict === 'drop') {
            return $this->done($request, $moderated);
        }

        Comment::create([
            'post_id' => $post->id,
            'parent_id' => $parent?->id,
            'locale' => app()->getLocale(),
            'author' => strip_tags($data['author']),
            'email' => $data['email'],
            'body' => $body,
            'status' => $verdict === 'spam' ? 'spam' : ($moderated ? 'pending' : 'approved'),
            'consent_at' => now(),
            'notify_consent_at' => $request->boolean('notify') ? now() : null,
        ]);

        return $this->done($request, $moderated);
    }

    private function done(Request $request, bool $moderated)
    {
        $message = __($moderated ? 'public.comment_pending' : 'public.comment_published');

        return $request->expectsJson() ? response()->json(['message' => $message], 201) : back()->with('status', $message);
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Public comment form of the article page (same fields as the prototype form). */
class CommentController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $post = Post::published()->whereHas('translations', fn ($t) => $t->where('locale', app()->getLocale())->where('slug', $slug))->firstOrFail();

        $moderated = (bool) Settings::get('commentsApproval');

        // Honeypot filled: a bot. Answer as if accepted so it learns nothing, store nothing.
        if (filled($request->input('website'))) {
            return $this->done($request, $moderated);
        }

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
            'author' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:100'],
            'consent' => ['accepted'],
        ]);

        Comment::create([
            'post_id' => $post->id,
            'locale' => app()->getLocale(),
            'author' => strip_tags($data['author']),
            'email' => $data['email'],
            'body' => strip_tags($data['comment']),          // plain text only: no links, no markup from readers
            'status' => $moderated ? 'pending' : 'approved',
            'consent_at' => now(),
        ]);

        return $this->done($request, $moderated);
    }

    private function done(Request $request, bool $moderated)
    {
        $message = __($moderated ? 'public.comment_pending' : 'public.comment_published');

        return $request->expectsJson() ? response()->json(['message' => $message], 201) : back()->with('status', $message);
    }
}

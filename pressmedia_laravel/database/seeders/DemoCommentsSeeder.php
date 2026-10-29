<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Seeder;

/** A few reader comments in each moderation state on the latest posts, to try the queue. Never run in production. */
class DemoCommentsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() || Comment::exists()) {
            return;
        }
        $posts = Post::published()->orderByDesc('published_at')->take(3)->get();
        $samples = [
            ['Awa D. (démo)', 'Merci pour cet éclairage précis et bien documenté.', 'approved'],
            ['Kofi A. (démo)', 'Article intéressant, j’aimerais une suite sur ce sujet.', 'pending'],
            ['Spam bot (démo)', 'Gagnez de l’argent rapidement !!!', 'spam'],
        ];
        foreach ($posts as $n => $post) {
            foreach ($samples as [$author, $body, $status]) {
                Comment::create(['post_id' => $post->id, 'locale' => 'fr', 'author' => $author, 'email' => 'lecteur'.$n.'@example.test',
                    'body' => $body, 'status' => $status, 'consent_at' => now()]);
            }
        }
    }
}

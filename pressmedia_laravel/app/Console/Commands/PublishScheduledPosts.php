<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Post;
use Illuminate\Console\Command;

/** EX-25: run every minute by the scheduler (cPanel cron `* * * * * php artisan schedule:run`). */
class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish scheduled posts whose publication time has come';

    public function handle(): int
    {
        $due = Post::where('status', 'scheduled')->where('published_at', '<=', now())->get();
        foreach ($due as $post) {
            $post->update(['status' => 'published']);
            AuditLog::system('publish', 'posts', 'post:'.$post->id, 'scheduled publication');
        }
        $this->info("Published {$due->count()} post(s).");

        return self::SUCCESS;
    }
}

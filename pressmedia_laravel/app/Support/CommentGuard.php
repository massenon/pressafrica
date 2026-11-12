<?php

namespace App\Support;

use App\Models\Comment;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Anti-bot and anti-flood rules of the public comment form (owner decision S5). Rate limits are on the route.
 * - drop: never stored, the sender still gets the usual "thank you" so a bot learns nothing;
 * - spam: stored for the moderators but never published.
 */
class CommentGuard
{
    /** A person needs a few seconds to read and type; scripts post at once. */
    public const MIN_SECONDS = 3;

    /** Old forms (cached page, tab left open) are refused after this. */
    public const MAX_AGE_HOURS = 48;

    /** Signed render time of the form, checked on submit (the browser cannot forge it). */
    public static function token(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    public static function verdict(?string $token, ?string $honeypot, int $postId, string $email, string $body): string
    {
        if (filled($honeypot) || ! self::tokenOk($token)) {
            return 'drop';
        }
        $norm = Str::lower(preg_replace('/\s+/u', ' ', trim($body)));
        $duplicate = Comment::withTrashed()->where('post_id', $postId)->where('created_at', '>=', now()->subDay())->pluck('body')
            ->contains(fn ($b) => Str::lower(preg_replace('/\s+/u', ' ', trim($b))) === $norm);
        if ($duplicate) {
            return 'drop';
        }
        if (preg_match_all('#https?://|www\.#i', $body) >= 2 || self::blacklisted($email, $body)) {
            return 'spam';
        }

        return 'ok';
    }

    private static function tokenOk(?string $token): bool
    {
        try {
            $age = now()->getTimestamp() - (int) Crypt::decryptString((string) $token);
        } catch (Throwable) {
            return false;
        }

        return $age >= self::MIN_SECONDS && $age <= self::MAX_AGE_HOURS * 3600;
    }

    /** Settings > Security > commentBlacklist: e-mails, domains (@example.com or example.com) or words, one per line. */
    private static function blacklisted(string $email, string $body): bool
    {
        $email = Str::lower($email);
        $domain = Str::after($email, '@');
        foreach (preg_split('/\R/', Str::lower((string) Settings::get('commentBlacklist'))) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if ($entry === $email || ltrim($entry, '@') === $domain || (! str_contains($entry, '@') && ! str_contains($entry, '.') && preg_match('/\b'.preg_quote($entry, '/').'\b/iu', $body))) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything the site holds about one e-mail address (GDPR access and erasure, EX-34).
 * The append-only audit log is not touched (security record, INV-02); the DPO decides its retention (Q8).
 */
class PersonalData
{
    public static function find(string $email): array
    {
        $email = Str::lower(trim($email));

        return [
            'email' => $email,
            'comments' => Comment::withTrashed()->whereRaw('lower(email) = ?', [$email])->get()
                ->map(fn ($c) => ['id' => $c->id, 'article' => $c->post?->translation($c->locale)?->title, 'author' => $c->author, 'text' => $c->body,
                    'status' => $c->status, 'created_at' => $c->created_at?->toIso8601String(), 'consent_at' => $c->consent_at?->toIso8601String(),
                    'reply_notification_consent_at' => $c->notify_consent_at?->toIso8601String()])->all(),
            'newsletter' => NewsletterSubscriber::where('email', $email)->get()
                ->map(fn ($s) => ['status' => $s->status, 'locale' => $s->locale, 'topics' => $s->topics, 'consent_at' => $s->consent_at?->toIso8601String(),
                    'confirmed_at' => $s->confirmed_at?->toIso8601String(), 'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String()])->all(),
            'contact_messages' => ContactMessage::whereRaw('lower(email) = ?', [$email])->get()
                ->map(fn ($m) => collect($m->toArray())->only(['first_name', 'last_name', 'organisation', 'phone', 'country', 'city', 'message', 'status', 'created_at', 'consent_at'])->all())->all(),
            'staff_account' => ($u = User::whereRaw('lower(email) = ?', [$email])->first()) ? ['name' => $u->name, 'roles' => $u->getRoleNames()->all(), 'created_at' => $u->created_at?->toIso8601String()] : null,
        ];
    }

    /** @return array<string, int> what was erased or anonymised */
    public static function erase(string $email): array
    {
        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($email) {
            $done = [
                // Comments stay in their threads, without the person: name replaced, e-mail and consents removed.
                'comments_anonymised' => Comment::withTrashed()->whereRaw('lower(email) = ?', [$email])->update([
                    'author' => __('site.cm_anonymous'), 'email' => 'erased-'.substr(hash('sha256', $email.config('app.key')), 0, 12).'@invalid',
                    'notify_consent_at' => null, 'notified_at' => null,
                ]),
                'newsletter_deleted' => NewsletterSubscriber::where('email', $email)->delete(),
                'contact_messages_deleted' => ContactMessage::whereRaw('lower(email) = ?', [$email])->delete(),
                'staff_account_anonymised' => 0,
            ];
            // A staff account cannot be deleted while its articles exist: it becomes "Former author", without its
            // e-mail, password or roles, and its articles stay published under that name (PRD R9).
            if ($u = User::whereRaw('lower(email) = ?', [$email])->first()) {
                $u->syncRoles([]);
                $u->tokens()->delete();
                $u->forceFill(['name' => __('site.former_author'), 'email' => 'former-'.$u->id.'@authors.invalid',
                    'password' => Str::random(64), 'remember_token' => null])->save();
                $done['staff_account_anonymised'] = 1;
            }
            AuditLog::record('erase', 'privacy', 'subject:'.substr(hash('sha256', $email), 0, 12), json_encode($done));

            return $done;
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'spam'];

    protected $fillable = ['post_id', 'parent_id', 'locale', 'author', 'email', 'body', 'status', 'consent_at', 'notify_consent_at', 'notified_at', 'moderated_by', 'staff_user_id'];

    /** A published comment shows the name and text only, never the e-mail (EX-26), even if serialised to JSON. */
    protected $hidden = ['email', 'moderated_by', 'notify_consent_at', 'notified_at'];

    protected $casts = ['consent_at' => 'datetime', 'notify_consent_at' => 'datetime', 'notified_at' => 'datetime'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Newsroom reply (badge "Rédaction"), signed with the staff member's name (owner decision S2). */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function isStaff(): bool
    {
        return $this->staff_user_id !== null;
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}

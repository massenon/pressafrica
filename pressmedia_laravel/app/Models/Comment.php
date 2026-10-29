<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'spam'];

    protected $fillable = ['post_id', 'locale', 'author', 'email', 'body', 'status', 'consent_at', 'moderated_by'];

    /** A published comment shows the name and text only, never the e-mail (EX-26), even if serialised to JSON. */
    protected $hidden = ['email', 'moderated_by'];

    protected $casts = ['consent_at' => 'datetime'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}

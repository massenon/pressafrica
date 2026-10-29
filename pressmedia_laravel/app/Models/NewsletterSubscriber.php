<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $guarded = [];

    protected $hidden = ['token'];

    protected $casts = ['topics' => 'array', 'consent_at' => 'datetime', 'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($s) => $s->token ??= Str::random(48));
    }

    public function confirmUrl(): string
    {
        return url("{$this->locale}/newsletter/confirm/{$this->token}");
    }

    public function unsubscribeUrl(): string
    {
        return url("{$this->locale}/newsletter/unsubscribe/{$this->token}");
    }
}

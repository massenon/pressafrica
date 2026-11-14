<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostTranslation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * False for a translation whose body is still in another language (title and summary translated only): it
     * duplicates the other version, so it stays out of the index, the sitemaps, the feed and the hreflang cluster.
     */
    public function isIndexable(): bool
    {
        return ! $this->body_locale || $this->body_locale === $this->locale;
    }

    /** Stored reading time for imported posts, else computed like the prototype. */
    public function minutes(): int
    {
        return $this->reading_minutes ?? Post::minutes($this->body);
    }
}

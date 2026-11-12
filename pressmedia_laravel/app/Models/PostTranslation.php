<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostTranslation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Stored reading time for imported posts, else computed like the prototype. */
    public function minutes(): int
    {
        return $this->reading_minutes ?? Post::minutes($this->body);
    }
}

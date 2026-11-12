<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeSlot extends Model
{
    public const SLOTS = ['hero' => 3, 'latest' => 6, 'econ_big' => 2, 'econ_small' => 4];

    public $timestamps = false;

    protected $fillable = ['slot', 'position', 'post_id'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
